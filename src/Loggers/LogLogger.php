<?php

declare(strict_types=1);

namespace MicroOTEL\Loggers;

use MicroOTEL\Encoders;
use MicroOTEL\Entries\LogEntry;

class LogLogger extends BaseLogger
{
    public function getPacket(): array
    {
        return [
            "resourceLogs" => [
                [
                    "resource" => [
                        "attributes" => Encoders::dict2otel($this->client->getResourceAttributes()),
                    ],
                    "scopeLogs" => [
                        [
                            "scope" => $this->client->getScope(),
                            "logRecords" => array_map(fn ($x) => $x->getPacket(), $this->data),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function log(LogEntry $entry): void
    {
        $this->data[] = $entry;
    }
}
