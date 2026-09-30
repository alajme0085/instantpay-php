<?php

declare(strict_types=1);

namespace InstantPay\Resources;

use InstantPay\Client;

abstract class Resource
{
    /** @var Client */
    protected $client;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    protected static function id(string $id): string
    {
        return rawurlencode($id);
    }
}
