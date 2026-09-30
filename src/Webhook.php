<?php

declare(strict_types=1);

namespace InstantPay;

/**
 * Webhook signatures: `InstantPay-Signature: t=<unix seconds>,v1=<hex HMAC-SHA256 of "t.body">`.
 * Always pass the raw request body (file_get_contents('php://input')), never re-encoded JSON.
 */
final class Webhook
{
    public const TOLERANCE_SECONDS = 300;

    public static function sign(string $payload, string $secret, ?int $timestamp = null): string
    {
        $t = $timestamp ?? time();
        return 't=' . $t . ',v1=' . hash_hmac('sha256', $t . '.' . $payload, $secret);
    }

    public static function verify(string $payload, ?string $header, string $secret, int $tolerance = self::TOLERANCE_SECONDS, ?int $now = null): bool
    {
        if ($header === null || $header === '') {
            return false;
        }
        $parts = [];
        foreach (explode(',', $header) as $piece) {
            $kv = explode('=', $piece, 2);
            if (count($kv) === 2) {
                $parts[trim($kv[0])] = trim($kv[1]);
            }
        }
        if (!isset($parts['t'], $parts['v1']) || !ctype_digit($parts['t'])) {
            return false;
        }
        $t = (int) $parts['t'];
        if (abs(($now ?? time()) - $t) > $tolerance) {
            return false;
        }
        $expected = hash_hmac('sha256', $t . '.' . $payload, $secret);
        return hash_equals($expected, $parts['v1']);
    }

    /**
     * Verifies, then decodes the event.
     *
     * @return array<string, mixed>
     * @throws InstantPayException when the signature does not match
     */
    public static function constructEvent(string $payload, ?string $header, string $secret, int $tolerance = self::TOLERANCE_SECONDS, ?int $now = null): array
    {
        if (!self::verify($payload, $header, $secret, $tolerance, $now)) {
            throw new InstantPayException('Webhook signature is not valid.', 400, 'invalid_signature');
        }
        $event = json_decode($payload, true);
        if (!is_array($event)) {
            throw new InstantPayException('Webhook body is not JSON.', 400, 'invalid_payload');
        }
        return $event;
    }
}
