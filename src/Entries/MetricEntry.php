<?php

declare(strict_types=1);

namespace MicroOTEL\Entries;

class MetricEntry extends BaseEntry
{
    public function __construct(
    ) {
    }

    /**
     * @return array<string, mixed>
     */
    public function getPacket(): array
    {
        return [];
    }
}
