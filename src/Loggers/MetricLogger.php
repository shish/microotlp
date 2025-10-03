<?php

declare(strict_types=1);

namespace MicroOTEL\Loggers;

use Google\Protobuf\Internal\Message;
use Opentelemetry\Proto\Metrics\V1\Metric;
use Opentelemetry\Proto\Metrics\V1\MetricsData;
use Opentelemetry\Proto\Metrics\V1\ResourceMetrics;
use Opentelemetry\Proto\Metrics\V1\ScopeMetrics;

/**
 * @extends BaseLogger<Metric>
 */
class MetricLogger extends BaseLogger
{
    public function getMessage(): Message
    {
        return new MetricsData([
            "resource_metrics" => [
                new ResourceMetrics([
                    "resource" => $this->client->getResource(),
                    "scope_metrics" => [
                        new ScopeMetrics([
                            "scope" => $this->client->getScope(),
                            "metrics" => $this->data,
                        ]),
                    ],
                ])
            ]
        ]);
    }

    public function log(Metric $entry): void
    {
        $this->data[] = $entry;
    }
}
