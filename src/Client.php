<?php

declare(strict_types=1);

namespace InstantPay;

use InstantPay\Resources\Beneficiaries;
use InstantPay\Resources\PaymentIntents;
use InstantPay\Resources\PaymentLinks;
use InstantPay\Resources\Payments;
use InstantPay\Resources\Payouts;
use InstantPay\Resources\Refunds;
use InstantPay\Resources\Wallets;
use InstantPay\Resources\WebhookEndpoints;

/**
 * Client for one API key.
 *
 *   $ip = new \InstantPay\Client('ipk_test_...', ['base_url' => 'https://instantpay-api.onrender.com']);
 *   $checkout = $ip->paymentIntents->create(['amount' => '250.00', 'description' => 'Order 1001']);
 */
class Client
{
    public const VERSION = '0.1.0';
    /** Production API. For the public demo use https://instantpay-api.onrender.com. */
    public const DEFAULT_BASE_URL = 'https://api.instantpay.sa';

    /** @var PaymentIntents */
    public $paymentIntents;
    /** @var PaymentLinks */
    public $paymentLinks;
    /** @var Payments */
    public $payments;
    /** @var Refunds */
    public $refunds;
    /** @var Payouts */
    public $payouts;
    /** @var Beneficiaries */
    public $beneficiaries;
    /** @var Wallets */
    public $wallets;
    /** @var WebhookEndpoints */
    public $webhookEndpoints;

    /** @var string */
    private $apiKey;
    /** @var string */
    private $baseUrl;
    /** @var float */
    private $timeout;
    /** @var int */
    private $maxRetries;
    /** @var callable */
    private $transport;

    /**
     * @param array{base_url?: string, timeout?: float, max_retries?: int, transport?: callable} $options
     *   transport: fn(string $method, string $url, array $headers, ?string $body, float $timeout): array{0:int,1:array<string,string>,2:string}
     */
    public function __construct(string $apiKey, array $options = [])
    {
        if (!preg_match('/^ipk_(test|live)_[0-9A-Za-z]{16,}$/', $apiKey)) {
            throw new \InvalidArgumentException('InstantPay: pass an API key like ipk_test_... (Portal > Developers).');
        }
        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim($options['base_url'] ?? self::DEFAULT_BASE_URL, '/');
        $this->timeout = (float) ($options['timeout'] ?? 30.0);
        $this->maxRetries = (int) ($options['max_retries'] ?? 2);
        $this->transport = $options['transport'] ?? [self::class, 'curlTransport'];

        $this->paymentIntents = new PaymentIntents($this);
        $this->paymentLinks = new PaymentLinks($this);
        $this->payments = new Payments($this);
        $this->refunds = new Refunds($this);
        $this->payouts = new Payouts($this);
        $this->beneficiaries = new Beneficiaries($this);
        $this->wallets = new Wallets($this);
        $this->webhookEndpoints = new WebhookEndpoints($this);
    }

    /**
     * @param array<string, mixed>|null $body
     * @param array<string, mixed> $query
     * @return mixed decoded JSON (arrays), or null for 204
     */
    public function request(string $method, string $path, ?array $body = null, array $query = [], ?string $idempotencyKey = null)
    {
        $url = $this->baseUrl . $path;
        $clean = [];
        foreach ($query as $k => $v) {
            if ($v === null || $v === '') {
                continue;
            }
            $clean[$k] = is_bool($v) ? ($v ? 'true' : 'false') : (string) $v;
        }
        if ($clean) {
            $url .= '?' . http_build_query($clean);
        }
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => 'instantpay-php/' . self::VERSION,
        ];
        $payload = null;
        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $payload = json_encode($body, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        if ($method !== 'GET') {
            // Retries of a write reuse the key, so the API does it once.
            $headers['Idempotency-Key'] = $idempotencyKey ?? self::uuid();
        }

        for ($attempt = 0; ; $attempt++) {
            try {
                [$status, $resHeaders, $raw] = call_user_func($this->transport, $method, $url, $headers, $payload, $this->timeout);
            } catch (\RuntimeException $e) {
                if ($attempt < $this->maxRetries) {
                    usleep(self::backoff($attempt));
                    continue;
                }
                throw new InstantPayException('Could not reach InstantPay: ' . $e->getMessage(), 0, 'network_error');
            }
            if (($status === 429 || $status >= 500) && $attempt < $this->maxRetries) {
                $after = $resHeaders['retry-after'] ?? '';
                usleep(ctype_digit($after) ? min((int) $after, 20) * 1000000 : self::backoff($attempt));
                continue;
            }
            if ($status === 204 || $raw === '') {
                return null;
            }
            $json = json_decode($raw, true);
            if ($status >= 400) {
                $err = is_array($json) && isset($json['error']) && is_array($json['error']) ? $json['error'] : [];
                throw new InstantPayException(
                    (string) ($err['message'] ?? ('HTTP ' . $status)),
                    $status,
                    (string) ($err['code'] ?? 'http_error'),
                    isset($err['param']) ? (string) $err['param'] : null,
                    $resHeaders['x-request-id'] ?? null
                );
            }
            return $json;
        }
    }

    /**
     * Yields every item across pages: foreach ($ip->payments->all(['state' => 'settled']) as $p) { ... }
     *
     * @param array<string, mixed> $params
     * @return \Generator<int, array<string, mixed>>
     */
    public function paginate(string $path, array $params = []): \Generator
    {
        while (true) {
            $page = $this->request('GET', $path, null, $params);
            foreach ($page['data'] ?? [] as $item) {
                yield $item;
            }
            if (empty($page['has_more']) || empty($page['next_cursor'])) {
                return;
            }
            $params['starting_after'] = $page['next_cursor'];
        }
    }

    /**
     * @param array<string, string> $headers
     * @return array{0:int, 1:array<string,string>, 2:string}
     */
    public static function curlTransport(string $method, string $url, array $headers, ?string $body, float $timeout): array
    {
        $ch = curl_init($url);
        $lines = [];
        foreach ($headers as $k => $v) {
            $lines[] = $k . ': ' . $v;
        }
        $resHeaders = [];
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $lines,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT_MS => (int) ($timeout * 1000),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_HEADERFUNCTION => static function ($ch, string $line) use (&$resHeaders): int {
                $kv = explode(':', $line, 2);
                if (count($kv) === 2) {
                    $resHeaders[strtolower(trim($kv[0]))] = trim($kv[1]);
                }
                return strlen($line);
            },
        ]);
        if ($body !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }
        $raw = curl_exec($ch);
        if ($raw === false) {
            $error = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException($error);
        }
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        return [$status, $resHeaders, (string) $raw];
    }

    private static function backoff(int $attempt): int
    {
        return (int) (min(8.0, 0.5 * (2 ** $attempt)) * 1000000) + random_int(0, 250000);
    }

    private static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
