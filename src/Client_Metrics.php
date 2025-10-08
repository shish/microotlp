<?php

declare(strict_types=1);

namespace MicroOTLP;

use MicroOTLP\MockTypes\AggregationTemporality;
use MicroOTLP\MockTypes\ExponentialHistogram;
use MicroOTLP\MockTypes\ExponentialHistogramDataPoint;
use MicroOTLP\MockTypes\Gauge;
use MicroOTLP\MockTypes\Histogram;
use MicroOTLP\MockTypes\HistogramDataPoint;
use MicroOTLP\MockTypes\Metric;
use MicroOTLP\MockTypes\MetricsData;
use MicroOTLP\MockTypes\NumberDataPoint;
use MicroOTLP\MockTypes\ResourceMetrics;
use MicroOTLP\MockTypes\ScopeMetrics;
use MicroOTLP\MockTypes\Sum;

trait Client_Metrics
{
    use Client_Utils;

    /** @var array<Metric> */
    protected array $metricData = [];

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
        $this->logMetric(
            $name,
            $unit,
            $description,
            $metadata,
            ["sum" => new Sum([
                "dataPoints" => [
                    new NumberDataPoint([
                        "asDouble" => $value,
                        "timeUnixNano" => $this->time(),
                        //"attributes" => $this->formatAttributes($metadata),
                    ]),
                ],
                "aggregationTemporality" => AggregationTemporality::AGGREGATION_TEMPORALITY_DELTA,
                "isMonotonic" => true,
            ])],
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
        $this->logMetric(
            $name,
            $unit,
            $description,
            $metadata,
            ["gauge" => new Gauge([
                "dataPoints" => [
                    new NumberDataPoint([
                        "asDouble" => $value,
                        "timeUnixNano" => $this->time(),
                    ]),
                ],
            ])],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<int> $bucketCounts
     * @param array<int> $explicitBounds
     */
    public function logHistogram(
        string $name,
        int $count,
        int $sum,
        array $bucketCounts,
        array $explicitBounds,
        int $min,
        int $max,
        ?string $unit = null,
        ?string $description = null,
        array $metadata = [],
    ): void {
        $this->logMetric(
            $name,
            $unit,
            $description,
            $metadata,
            ["histogram" => new Histogram([
                "aggregationTemporality" => AggregationTemporality::AGGREGATION_TEMPORALITY_DELTA,
                "dataPoints" => [
                    new HistogramDataPoint([
                        // "startTimeUnixNano",
                        "timeUnixNano" => $this->time(),
                        "count" => $count,
                        "sum" => $sum,
                        "bucketCounts" => $bucketCounts,
                        "explicitBounds" => $explicitBounds,
                        "min" => $min,
                        "max" => $max,
                    ]),
                ],
            ])],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array{offset: int, bucketCounts: array<int>} $positive
     */
    public function logExponentialHistogram(
        string $name,
        int $count,
        int $sum,
        int $scale,
        int $zeroCount,
        array $positive,
        int $min,
        int $max,
        int $zeroThreshold,
        ?string $unit = null,
        ?string $description = null,
        array $metadata = [],
    ): void {
        $this->logMetric(
            $name,
            $unit,
            $description,
            $metadata,
            ["exponentialHistogram" => new ExponentialHistogram([
                "aggregationTemporality" => AggregationTemporality::AGGREGATION_TEMPORALITY_DELTA,
                "dataPoints" => [
                    new ExponentialHistogramDataPoint([
                        // "startTimeUnixNano",
                        "timeUnixNano" => $this->time(),
                        "count" => $count,
                        "sum" => $sum,
                        "scale" => $scale,
                        "zeroCount" => $zeroCount,
                        "positive" => $positive,
                        "min" => $min,
                        "max" => $max,
                        "zeroThreshold" => $zeroThreshold,
                    ]),
                ],
            ])],
        );
    }

    /**
     * @param array<string, mixed> $metadata
     * @param array<string, Gauge|Sum|Histogram|ExponentialHistogram> $metric
     */
    private function logMetric(
        string $name,
        ?string $unit = null,
        ?string $description = null,
        array $metadata = [],
        array $metric = [],
    ): void {
        if ($metadata) {
            $metric["metadata"] = self::dict2otel($metadata);
        }
        $this->metricData[] = new Metric(array_merge([
            "name" => $name,
            "description" => $description,
            "unit" => $unit,
        ], $metric));
    }

    public function flushMetrics(?string $url = null): void
    {
        if ($this->metricData) {
            $this->sendData(
                $this->getTransportUrl($url),
                "metrics",
                new MetricsData([
                    "resourceMetrics" => [
                        new ResourceMetrics([
                            "resource" => $this->getResource(),
                            "scopeMetrics" => [
                                new ScopeMetrics([
                                    "scope" => $this->getScope(),
                                    "metrics" => $this->metricData,
                                ]),
                            ],
                        ])
                    ]
                ])
            );
            $this->metricData = [];
        }
    }
}
