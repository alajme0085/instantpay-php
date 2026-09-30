<?php

declare(strict_types=1);

namespace InstantPay\Resources;

/** Online checkout: send the customer to checkout_url; confirm with the webhook or retrieve(). */
final class PaymentIntents extends Resource
{
    /** @param array<string, mixed> $params amount, description, reference?, return_url? */
    public function create(array $params, ?string $idempotencyKey = null): array
    {
        return $this->client->request('POST', '/v1/payment_intents', $params, [], $idempotencyKey);
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/payment_intents/' . self::id($id));
    }

    /** @param array<string, mixed> $params */
    public function list(array $params = []): array
    {
        return $this->client->request('GET', '/v1/payment_intents', null, $params);
    }

    /** @param array<string, mixed> $params */
    public function all(array $params = []): \Generator
    {
        return $this->client->paginate('/v1/payment_intents', $params);
    }
}
