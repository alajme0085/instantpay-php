<?php

declare(strict_types=1);

namespace InstantPay\Resources;

final class Wallets extends Resource
{
    public function list(): array
    {
        return $this->client->request('GET', '/v1/wallets');
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/wallets/' . self::id($id));
    }

    /** @param array<string, mixed> $params */
    public function transactions(string $id, array $params = []): array
    {
        return $this->client->request('GET', '/v1/wallets/' . self::id($id) . '/transactions', null, $params);
    }
}
