<?php

declare(strict_types=1);

namespace MicroOTEL\Loggers;

use MicroOTEL\Encoders;
use MicroOTEL\Entries\TraceEntry;

class TraceLogger extends BaseLogger
{
    public function getPacket(): array
    {
        return [
            "resourceSpans" => [
                [
                    "resource" => [
                        "attributes" => Encoders::dict2otel($this->client->getResourceAttributes()),
                    ],
                    "scopeSpans" => [
                        [
                            "scope" => $this->client->getScope(),
                            "spans" => array_map(fn ($x) => $x->getPacket(), $this->data),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function startSpan(string $name): TraceEntry
    {
        $entry = new TraceEntry($this, $name);
        $this->data[] = $entry;
        return $entry;
    }
}
