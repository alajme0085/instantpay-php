<?php

declare(strict_types=1);

namespace InstantPay\Resources;

final class Payments extends Resource
{
    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/payments/' . self::id($id));
    }

    /** @param array<string, mixed> $params channel, state (approved|settled), q (order number, RRN, last 4) */
    public function list(array $params = []): array
    {
        return $this->client->request('GET', '/v1/payments', null, $params);
    }

    /** @param array<string, mixed> $params */
    public function all(array $params = []): \Generator
    {
        return $this->client->paginate('/v1/payments', $params);
    }
}
