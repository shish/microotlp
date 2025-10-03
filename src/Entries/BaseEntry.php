<?php

declare(strict_types=1);

namespace MicroOTEL\Entries;

abstract class BaseEntry
{
    public function __construct(
        protected readonly \MicroOTEL\Client $client,
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    abstract public function getPacket(): array;
}
