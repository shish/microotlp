<?php

declare(strict_types=1);

namespace MicroOTEL\Loggers;

use Google\Protobuf\Internal\Message;

/**
 * @template T
 */
abstract class BaseLogger
{
    /** @var array<T> */
    protected array $data = [];

    public function __construct(
        public \MicroOTEL\Client $client
    ) {
    }

    abstract public function getMessage(): Message;

    public function hasData(): bool
    {
        return !empty($this->data);
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
