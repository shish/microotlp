<?php

declare(strict_types=1);

namespace MicroOTLP\Loggers;

use Google\Protobuf\Internal\Message;
use Opentelemetry\Proto\Metrics\V1\ExponentialHistogramDataPoint;
use Opentelemetry\Proto\Metrics\V1\Gauge;
use Opentelemetry\Proto\Metrics\V1\Sum;
use Opentelemetry\Proto\Metrics\V1\Histogram;
use Opentelemetry\Proto\Metrics\V1\ExponentialHistogram;
use Opentelemetry\Proto\Metrics\V1\HistogramDataPoint;
use Opentelemetry\Proto\Metrics\V1\NumberDataPoint;
use Opentelemetry\Proto\Metrics\V1\Metric;
use Opentelemetry\Proto\Metrics\V1\MetricsData;
use Opentelemetry\Proto\Metrics\V1\ResourceMetrics;
use Opentelemetry\Proto\Metrics\V1\ScopeMetrics;
use Opentelemetry\Proto\Metrics\V1\AggregationTemporality;

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

    /**
     * @param array<string, mixed> $metadata
     */
    public function logCounter(
        string $name,
        float $value = 0,
        ?string $unit = null,
        ?string $description = null,
        array $metadata = [],
    ): void {
        $this->log(
            $name,
            $unit,
            $description,
            $metadata,
            sum: new Sum([
                "data_points" => [
                    new NumberDataPoint([
                        "as_double" => $value,
                        "time_unix_nano" => (int) (microtime(true) * 1e9),
                        //"attributes" => $this->formatAttributes($metadata),
                    ]),
                ],
                "aggregation_temporality" => AggregationTemporality::AGGREGATION_TEMPORALITY_DELTA,
                "is_monotonic" => true,
            ]),
        );
    }

    /**
     * @param array<string, mixed> $metadata
     */
    public function logGauge(
        string $name,
        float $value,
        ?string $unit = null,
        ?string $description = null,
        array $metadata = [],
    ): void {
        $this->log(
            $name,
            $unit,
            $description,
            $metadata,
            gauge: new Gauge([
                "data_points" => [
                    new NumberDataPoint([
                        "as_double" => $value,
                        "time_unix_nano" => (int) (microtime(true) * 1_000_000_000),
                    ]),
                ],
            ]),
        );
    }

    /*
     * @param array<string, mixed> $metadata
    public function logHistogram(
        string $name,
        float $value,
        ?string $unit = null,
        ?string $description = null,
        array $metadata = [],
    ): void {
        $this->log(
            $name,
            $unit,
            $description,
            $metadata,
            histogram: new Histogram([
                "data_points" => [
                    new HistogramDataPoint([
                        "value" => $value,
                        "time_unix_nano" => (int) (microtime(true) * 1_000_000_000),
                    ]),
                ],
            ]),
        );
    }

     * @param array<string, mixed> $metadata
    public function logExponentialHistogram(
        string $name,
        float $value,
        ?string $unit = null,
        ?string $description = null,
        array $metadata = [],
    ): void {
        $this->log(
            $name,
            $unit,
            $description,
            $metadata,
            exponential_histogram: new ExponentialHistogram([
                "data_points" => [
                    new ExponentialHistogramDataPoint([
                        "value" => $value,
                        "time_unix_nano" => (int) (microtime(true) * 1_000_000_000),
                    ]),
                ],
            ]),
        );
    }
    */

    /**
     * @param array<string, mixed> $metadata
     */
    public function log(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array $metadata = [],
        ?Gauge $gauge = null,
        ?Sum $sum = null,
        ?Histogram $histogram = null,
        ?ExponentialHistogram $exponential_histogram = null,
    ): void {
        $this->data[] = new Metric([
            "name" => $name,
            "description" => $description,
            "unit" => $unit,
            "metadata" => $metadata,
            "gauge" => $gauge,
            "sum" => $sum,
            "histogram" => $histogram,
            "exponential_histogram" => $exponential_histogram,
        ]);
    }
}
