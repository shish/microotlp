<?php

declare(strict_types=1);

namespace MicroOTEL\Loggers;

use MicroOTEL\Entries\BaseEntry;

abstract class BaseLogger
{
    /** @var array<BaseEntry> */
    protected array $data = [];

    public function __construct(
        public \MicroOTEL\Client $client
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function getPacket(): array;

    public function hasData(): bool
    {
        return !empty($this->data);
    }

    public function clear(): void
    {
        $this->data = [];
    }
}
