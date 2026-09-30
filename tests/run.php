<?php

declare(strict_types=1);

// Dependency-free tests: php tests/run.php
// With INSTANTPAY_API_KEY (a sandbox key) set, also runs against a real API
// (INSTANTPAY_API_URL, default http://localhost:4000).

require __DIR__ . '/../src/autoload.php';

use InstantPay\Client;
use InstantPay\InstantPayException;
use InstantPay\Webhook;

const KEY = 'ipk_test_abcdefghijklmnopqrstuvwxyz012345';
$failures = 0;
$count = 0;

function check(string $name, callable $fn): void
{
    global $failures, $count;
    $count++;
    try {
        $fn();
        echo "ok   $name\n";
    } catch (\Throwable $e) {
        $failures++;
        echo "FAIL $name: " . $e->getMessage() . ' (' . basename($e->getFile()) . ':' . $e->getLine() . ")\n";
    }
}

function same($expected, $actual, string $what = ''): void
{
    if ($expected !== $actual) {
        throw new \RuntimeException($what . ' expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

/** A transport that replays canned responses and records the calls. */
function fake(array $responses, array &$calls): callable
{
    return static function (string $method, string $url, array $headers, ?string $body, float $timeout) use (&$responses, &$calls): array {
        $calls[] = [$method, $url, $headers, $body];
        $next = array_shift($responses);
        if ($next instanceof \Throwable) {
            throw $next;
        }
        [$status, $json, $h] = $next + [2 => []];
        return [$status, $h, $json === null ? '' : json_encode($json)];
    };
}

check('refuses something that is not an API key', function () {
    try {
        new Client('sk_live_x');
    } catch (\InvalidArgumentException $e) {
        return;
    }
    throw new \RuntimeException('no exception');
});

check('retries a write with the same idempotency key', function () {
    $calls = [];
    $ip = new Client(KEY, ['base_url' => 'https://api.test/', 'transport' => fake([
        [503, ['error' => ['code' => 'x', 'message' => 'later']], ['retry-after' => '0']],
        [201, ['id' => 'pi_1']],
    ], $calls)]);
    $pi = $ip->paymentIntents->create(['amount' => '10.00', 'description' => 'Order 1']);
    same('pi_1', $pi['id']);
    same(2, count($calls));
    same('https://api.test/v1/payment_intents', $calls[0][1]);
    same('Bearer ' . KEY, $calls[0][2]['Authorization']);
    same($calls[0][2]['Idempotency-Key'], $calls[1][2]['Idempotency-Key']);
    same(['amount' => '10.00', 'description' => 'Order 1'], json_decode($calls[0][3], true));
});

check('turns API errors into InstantPayException', function () {
    $calls = [];
    $ip = new Client(KEY, ['base_url' => 'https://api.test', 'transport' => fake([
        [422, ['error' => ['code' => 'insufficient_funds', 'message' => 'No.', 'param' => 'amount']], ['x-request-id' => 'req_1']],
    ], $calls)]);
    try {
        $ip->refunds->create(['payment_id' => 'pay_1', 'amount' => '5.00', 'reason' => 'x']);
    } catch (InstantPayException $e) {
        same([422, 'insufficient_funds', 'amount', 'req_1'], [$e->getStatus(), $e->getErrorCode(), $e->getParam(), $e->getRequestId()]);
        return;
    }
    throw new \RuntimeException('no exception');
});

check('gives up after retries on network errors', function () {
    $calls = [];
    $ip = new Client(KEY, ['base_url' => 'https://api.test', 'max_retries' => 1, 'transport' => fake([
        new \RuntimeException('down'), new \RuntimeException('down'),
    ], $calls)]);
    try {
        $ip->payments->retrieve('pay_1');
    } catch (InstantPayException $e) {
        same('network_error', $e->getErrorCode());
        same(2, count($calls));
        return;
    }
    throw new \RuntimeException('no exception');
});

check('walks every page', function () {
    $calls = [];
    $ip = new Client(KEY, ['base_url' => 'https://api.test', 'transport' => fake([
        [200, ['data' => [['id' => 'a'], ['id' => 'b']], 'has_more' => true, 'next_cursor' => 'b']],
        [200, ['data' => [['id' => 'c']], 'has_more' => false, 'next_cursor' => null]],
    ], $calls)]);
    $ids = [];
    foreach ($ip->payments->all(['limit' => 2, 'pos' => true]) as $p) {
        $ids[] = $p['id'];
    }
    same(['a', 'b', 'c'], $ids);
    same(true, strpos($calls[1][1], 'starting_after=b') !== false, 'cursor');
    same(true, strpos($calls[0][1], 'pos=true') !== false, 'bool query');
});

check('webhook signatures', function () {
    $secret = 'whsec_test123';
    $body = '{"id":"evt_1","object":"event","type":"payment.succeeded","data":{}}';
    $header = Webhook::sign($body, $secret, 1000000);
    same(true, Webhook::verify($body, $header, $secret, 300, 1000010));
    same(false, Webhook::verify($body . ' ', $header, $secret, 300, 1000010));
    same(false, Webhook::verify($body, $header, 'whsec_other', 300, 1000010));
    same(false, Webhook::verify($body, $header, $secret, 300, 1000400));
    same(false, Webhook::verify($body, null, $secret));
    same('payment.succeeded', Webhook::constructEvent($body, $header, $secret, 300, 1000000)['type']);
    same('t=1700000000,v1=' . hash_hmac('sha256', '1700000000.{}', 'whsec_x'), Webhook::sign('{}', 'whsec_x', 1700000000));
});

$live = getenv('INSTANTPAY_API_KEY');
if ($live) {
    check('live sandbox: checkout and payment link', function () use ($live) {
        $ip = new Client($live, ['base_url' => getenv('INSTANTPAY_API_URL') ?: 'http://localhost:4000']);
        $pi = $ip->paymentIntents->create([
            'amount' => '12.34',
            'description' => 'PHP SDK order',
            'reference' => 'php-' . time(),
            'return_url' => 'https://shop.example.sa/thanks',
        ]);
        same(1, preg_match('#/pay/[a-z0-9]{10}$#', $pi['checkout_url']));
        same('requires_payment', $ip->paymentIntents->retrieve($pi['id'])['status']);
        $link = $ip->paymentLinks->create(['amount' => '5.00', 'description' => 'PHP link']);
        same('cancelled', $ip->paymentLinks->cancel($link['id'])['status']);
        try {
            $ip->payments->retrieve('pay_00000000000000000000000000');
        } catch (InstantPayException $e) {
            same(404, $e->getStatus());
            return;
        }
        throw new \RuntimeException('expected 404');
    });
} else {
    echo "skip live sandbox (set INSTANTPAY_API_KEY)\n";
}

echo "\n" . ($count - $failures) . "/$count passed\n";
exit($failures ? 1 : 0);
