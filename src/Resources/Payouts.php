<?php

declare(strict_types=1);

namespace InstantPay\Resources;

final class Payouts extends Resource
{
    /** @param array<string, mixed> $params */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        $params += ['currency' => 'SAR'];
        return $this->client->request('POST', '/v1/payouts', $params, [], $idempotencyKey);
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/payouts/' . self::id($id));
    }

    /** @param array<string, mixed> $params */
    public function list(array $params = []): array
    {
        return $this->client->request('GET', '/v1/payouts', null, $params);
    }

    /** @param array<string, mixed> $params */
    public function all(array $params = []): \Generator
    {
        return $this->client->paginate('/v1/payouts', $params);
    }
}
