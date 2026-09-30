<?php

declare(strict_types=1);

namespace InstantPay\Resources;

/** Full or partial; the amount leaves your wallet at once. */
final class Refunds extends Resource
{
    /** @param array<string, mixed> $params */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        return $this->client->request('POST', '/v1/refunds', $params, [], $idempotencyKey);
    }
}
