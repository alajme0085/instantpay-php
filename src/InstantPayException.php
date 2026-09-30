<?php

declare(strict_types=1);

namespace InstantPay;

/**
 * Every API failure. getErrorCode() is stable (e.g. "insufficient_funds"); getMessage() is for people.
 */
class InstantPayException extends \RuntimeException
{
    /** @var int */
    private $status;
    /** @var string */
    private $errorCode;
    /** @var string|null */
    private $param;
    /** @var string|null */
    private $requestId;

    public function __construct(string $message, int $status, string $errorCode, ?string $param = null, ?string $requestId = null)
    {
        parent::__construct($message, $status);
        $this->status = $status;
        $this->errorCode = $errorCode;
        $this->param = $param;
        $this->requestId = $requestId;
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public function getErrorCode(): string
    {
        return $this->errorCode;
    }

    public function getParam(): ?string
    {
        return $this->param;
    }

    public function getRequestId(): ?string
    {
        return $this->requestId;
    }
}
