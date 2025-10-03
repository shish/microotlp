<?php

declare(strict_types=1);

namespace MicroOTEL\Loggers;

use MicroOTEL\Encoders;
use MicroOTEL\Entries\MetricEntry;

class MetricLogger extends BaseLogger
{
    public function getPacket(): array
    {
        return [
            "resourceMetrics" => [
                [
                    "resource" => [
                        "attributes" => Encoders::dict2otel($this->client->getResourceAttributes()),
                    ],
                    "scopeMetrics" => [
                        [
                            "scope" => $this->client->getScope(),
                            "metrics" => array_map(fn ($x) => $x->getPacket(), $this->data),
                        ],
                    ],
                ],
            ],
        ];
    }

    public function log(MetricEntry $entry): void
    {
        $this->data[] = $entry;
    }
}
