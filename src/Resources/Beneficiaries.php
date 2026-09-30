<?php

declare(strict_types=1);

namespace InstantPay\Resources;

final class Beneficiaries extends Resource
{
    public function list(): array
    {
        return $this->client->request('GET', '/v1/beneficiaries');
    }
}
