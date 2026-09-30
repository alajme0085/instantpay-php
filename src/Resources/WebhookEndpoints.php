<?php

declare(strict_types=1);

namespace InstantPay\Resources;

final class WebhookEndpoints extends Resource
{
    /**
     * The response carries the signing secret once; store it.
     *
     * @param array<string, mixed> $params url, events, description?
     */
    public function create(array $params): array
    {
        return $this->client->request('POST', '/v1/webhook_endpoints', $params);
    }

    public function list(): array
    {
        return $this->client->request('GET', '/v1/webhook_endpoints');
    }

    public function retrieve(string $id): array
    {
        return $this->client->request('GET', '/v1/webhook_endpoints/' . self::id($id));
    }

    public function delete(string $id): void
    {
        $this->client->request('DELETE', '/v1/webhook_endpoints/' . self::id($id));
    }
}
