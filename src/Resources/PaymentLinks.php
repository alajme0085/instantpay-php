<?php

declare(strict_types=1);

namespace InstantPay\Resources;

final class PaymentLinks extends Resource
{
    /** @param array<string, mixed> $params */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        $params += ['currency' => 'SAR', 'expires_in_days' => 7];
        return $this->client->request('POST', '/v1/payment_links', $params, [], $idempotencyKey);
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/payment_links/' . self::id($id));
    }

    /** @param array<string, mixed> $params */
    public function list(array $params = []): array
    {
        return $this->client->request('GET', '/v1/payment_links', null, $params);
    }

    /** @param array<string, mixed> $params */
    public function all(array $params = []): \Generator
    {
        return $this->client->paginate('/v1/payment_links', $params);
    }

    public function cancel(string $id): array
    {
        return $this->client->request('POST', '/v1/payment_links/' . self::id($id) . '/cancel');
    }
}
