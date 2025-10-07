<?php

declare(strict_types=1);

namespace MicroOTLP;

/*
use Google\Protobuf\Internal\Message;
use Opentelemetry\Proto\Common\V1\AnyValue;
use Opentelemetry\Proto\Common\V1\ArrayValue;
use Opentelemetry\Proto\Common\V1\InstrumentationScope;
use Opentelemetry\Proto\Common\V1\KeyValue;
use Opentelemetry\Proto\Common\V1\KeyValueList;
use Opentelemetry\Proto\Logs\V1\LogRecord;
use Opentelemetry\Proto\Logs\V1\LogsData;
use Opentelemetry\Proto\Logs\V1\ResourceLogs;
use Opentelemetry\Proto\Logs\V1\ScopeLogs;
use Opentelemetry\Proto\Metrics\V1\AggregationTemporality;
use Opentelemetry\Proto\Metrics\V1\ExponentialHistogram;
use Opentelemetry\Proto\Metrics\V1\Gauge;
use Opentelemetry\Proto\Metrics\V1\Histogram;
use Opentelemetry\Proto\Metrics\V1\Metric;
use Opentelemetry\Proto\Metrics\V1\MetricsData;
use Opentelemetry\Proto\Metrics\V1\NumberDataPoint;
use Opentelemetry\Proto\Metrics\V1\ResourceMetrics;
use Opentelemetry\Proto\Metrics\V1\ScopeMetrics;
use Opentelemetry\Proto\Metrics\V1\Sum;
use Opentelemetry\Proto\Resource\V1\Resource;
use Opentelemetry\Proto\Trace\V1\ResourceSpans;
use Opentelemetry\Proto\Trace\V1\ScopeSpans;
use Opentelemetry\Proto\Trace\V1\Span;
use Opentelemetry\Proto\Trace\V1\TracesData;
*/

use MicroOTLP\MockTypes\AggregationTemporality;
use MicroOTLP\MockTypes\AnyValue;
use MicroOTLP\MockTypes\ArrayValue;
use MicroOTLP\MockTypes\ExponentialHistogram;
use MicroOTLP\MockTypes\Gauge;
use MicroOTLP\MockTypes\Histogram;
use MicroOTLP\MockTypes\InstrumentationScope;
use MicroOTLP\MockTypes\KeyValue;
use MicroOTLP\MockTypes\KeyValueList;
use MicroOTLP\MockTypes\LogRecord;
use MicroOTLP\MockTypes\LogsData;
use MicroOTLP\MockTypes\Message;
use MicroOTLP\MockTypes\Metric;
use MicroOTLP\MockTypes\MetricsData;
use MicroOTLP\MockTypes\NumberDataPoint;
use MicroOTLP\MockTypes\Resource;
use MicroOTLP\MockTypes\ResourceLogs;
use MicroOTLP\MockTypes\ResourceMetrics;
use MicroOTLP\MockTypes\ResourceSpans;
use MicroOTLP\MockTypes\ScopeLogs;
use MicroOTLP\MockTypes\ScopeMetrics;
use MicroOTLP\MockTypes\ScopeSpans;
use MicroOTLP\MockTypes\Span;
use MicroOTLP\MockTypes\Sum;
use MicroOTLP\MockTypes\TracesData;

class Client
{
    private ?string $transportUrl;

    /** @var array<string, mixed> */
    private array $resourceAttributes = [];
    /** @var array<string, mixed> */
    private array $scopeAttributes = [];
    public readonly string $traceId;
    public readonly string $spanId;
    /** @var SpanBuilder[] */
    public array $spanStack = [];

    /** @var array<LogRecord> */
    protected array $logData = [];

    /** @var array<Metric> */
    protected array $metricData = [];

    /** @var array<Span> */
    protected array $traceData = [];

    /**
     * @param array<string, mixed>|null $resourceAttributes
     * @param array<string, mixed>|null $scopeAttributes
     */
    public function __construct(
        ?string $targetUrl = null,
        ?string $traceId = null,
        ?string $spanId = null,
        ?array $resourceAttributes = null,
        ?array $scopeAttributes = null,
    ) {
        $this->transportUrl = $targetUrl;
        $this->resourceAttributes = $resourceAttributes ?? [
            "service.name" => "microotel-service",
            "service.instance.id" => gethostname() ?: "unknown",
        ];
        $this->scopeAttributes = $scopeAttributes ?: [];

        $traceparent = $_SERVER['HTTP_TRACEPARENT'] ?? "";
        assert(is_string($traceparent));
        $parts = explode("-", $traceparent);
        if (count($parts) === 4) {
            $traceId = $traceId ?: $parts[1];
            $spanId = $spanId ?: $parts[2];
        }
        $this->traceId = $traceId ?: bin2hex(random_bytes(16));
        $this->spanId = $spanId ?: '0000000000000000';
        $this->spanStack = [];
    }

    public function getResource(): Resource
    {
        return new Resource([
            "attributes" => self::dict2otel($this->resourceAttributes),
        ]);
    }

    public function getScope(): InstrumentationScope
    {
        return new InstrumentationScope([
            "name" => "microotlp",
            "version" => "0.0.0",
            "attributes" => self::dict2otel($this->scopeAttributes)
        ]);
    }

    ///////////////////////////////////////////////////////////////////
    // Utils
    ///////////////////////////////////////////////////////////////////

    /**
     * @param array<mixed, mixed> $in
     * @return array<KeyValue>
     * */
    public static function dict2otel(array $in): array
    {
        $out = [];
        foreach ($in as $k => $v) {
            if ($v === null) {
                continue;
            }
            $out[] = new KeyValue([
                "key" => (string)$k,
                "value" => self::value2otel($v)
            ]);
        }
        return $out;
    }

    public static function value2otel(mixed $v): AnyValue
    {
        if (is_bool($v)) {
            return new AnyValue(['boolValue' => $v]);
        } elseif (is_int($v)) {
            return new AnyValue(['intValue' => $v]);
        } elseif (is_float($v)) {
            return new AnyValue(['doubleValue' => $v]);
        } elseif (is_string($v)) {
            return new AnyValue(['stringValue' => $v]);
        } elseif (is_array($v)) {
            if (array_is_list($v)) {
                return new AnyValue([
                    'arrayValue' => new ArrayValue([
                        'values' => array_map(fn ($x) => self::value2otel($x), $v)
                    ]),
                ]);
            } else {
                return new AnyValue([
                    'kvlistValue' => new KeyValueList([
                        'values' => self::dict2otel($v)
                    ])
                ]);
            }
        } elseif ($v instanceof \Stringable) {
            return new AnyValue(['stringValue' => (string)$v]);
        } else {
            throw new \InvalidArgumentException('Unsupported attribute value type: ' . gettype($v));
        }
    }

    public static function encodeId(string $id): string
    {
        // OTLP expects hex strings, but Protobuf encodes bytes as base64 --
        // if we preemptively _decode_ the hex string as if it were base64,
        // then base64'ing it will return the original hex string.
        //return base64_decode($id);

        return $id;
    }

    ///////////////////////////////////////////////////////////////////
    // Transport and Flush
    ///////////////////////////////////////////////////////////////////

    private function getTransportUrl(?string $url): string
    {
        if ($url !== null) {
            return $url;
        }
        if ($this->transportUrl !== null) {
            return $this->transportUrl;
        }
        throw new \RuntimeException("Transport is not set");
    }

    public function flushLogs(?string $url = null): void
    {
        if ($this->logData) {
            $this->sendData(
                $this->getTransportUrl($url),
                "logs",
                new LogsData([
                    "resourceLogs" => [
                        new ResourceLogs([
                            "resource" => $this->getResource(),
                            "scopeLogs" => [
                                new ScopeLogs([
                                    "scope" => $this->getScope(),
                                    "logRecords" => $this->logData,
                                ]),
                            ],
                        ])
                    ]
                ])
            );
            $this->logData = [];
        }
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

    public function flushTraces(?string $url = null): void
    {
        if ($this->traceData) {
            $this->sendData(
                $this->getTransportUrl($url),
                "traces",
                new TracesData([
                    "resourceSpans" => [
                        new ResourceSpans([
                            "resource" => $this->getResource(),
                            "scopeSpans" => [
                                new ScopeSpans([
                                    "scope" => $this->getScope(),
                                    "spans" => $this->traceData,
                                ]),
                            ],
                        ])
                    ]
                ])
            );
            $this->traceData = [];
        }
    }

    public function flush(?string $url = null): void
    {
        $url = $this->getTransportUrl($url);
        $this->flushLogs($url);
        $this->flushMetrics($url);
        $this->flushTraces($url);
    }

    private function sendData(string $url, string $api, Message $data): void
    {
        if (!str_contains($url, "://")) {
            if (
                str_ends_with($url, ".json")
                || str_ends_with($url, ".jsonl")
            ) {
                $url = "file://$url";
            } else {
                $url = "dir://$url";
            }
        }

        [$scheme, $path] = explode("://", $url, 2) + [1 => ''];
        match($scheme) {
            'http', 'https' => $this->sendDataToHTTP($url, $api, $data),
            'dir' => $this->sendDataToFile("$path/$api.jsonl", $data),
            'file' => $this->sendDataToFile($path, $data),
            default => throw new \InvalidArgumentException("Unsupported URL scheme: {$scheme}"),
        };
    }

    private function sendDataToFile(string $filename, Message $data): void
    {
        // $json = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to serialize to JSON: " . json_last_error_msg());
        }
        file_put_contents($filename, "$json\n", FILE_APPEND | LOCK_EX);
    }

    private function sendDataToHTTP(string $base, string $api, Message $data): void
    {
        // $json = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to serialize to JSON: " . json_last_error_msg());
        }
        $ch = curl_init("$base/v1/$api");
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        $ret = curl_exec($ch);
        if ($ret === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: $err");
        }
        curl_close($ch);
    }

    ///////////////////////////////////////////////////////////////////
    // Log Logger
    ///////////////////////////////////////////////////////////////////

    /**
     * @param array<string, mixed> $attributes
     */
    public function logMessage(string $message, array $attributes = []): void
    {
        $this->logData[] = new LogRecord([
            "timeUnixNano" => (string)(microtime(true) * 1e9),
            "observedTimeUnixNano" => (string)(microtime(true) * 1e9),
            "severityNumber" => 10, // INFO
            "severityText" => "Information",
            "body" => new AnyValue(["stringValue" => $message]),
            "attributes" => self::dict2otel($attributes),
            "traceId" => self::encodeId($this->traceId),
            "spanId" => self::encodeId(
                $this->spanStack
                    ? end($this->spanStack)->id
                    : $this->spanId
            ),
        ]);
    }

    ///////////////////////////////////////////////////////////////////
    // Metric Logger
    ///////////////////////////////////////////////////////////////////

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
                        "timeUnixNano" => (int) (microtime(true) * 1e9),
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
                        "timeUnixNano" => (int) (microtime(true) * 1_000_000_000),
                    ]),
                ],
            ])],
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
        $this->logMetric(
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
        $this->logMetic(
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
            $metric["metadata"] = $metadata;
        }
        $this->metricData[] = new Metric(array_merge([
            "name" => $name,
            "description" => $description,
            "unit" => $unit,
        ], $metric));
    }

    ///////////////////////////////////////////////////////////////////
    // Trace Logger
    ///////////////////////////////////////////////////////////////////

    /**
     * @param array<string, mixed> $attributes
     */
    public function startSpan(string $name, array $attributes = [], ?int $startTime = null): SpanBuilder
    {
        return new SpanBuilder($this, $name, $attributes, startTime: $startTime);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function endSpan(
        ?bool $success = null,
        ?string $message = null,
        ?array $attributes = null,
        ?int $endTime = null,
    ): void {
        if (empty($this->spanStack)) {
            throw new \RuntimeException("No active span to end");
        }
        $sb = end($this->spanStack);
        $sb->end($success, $message, $attributes, endTime: $endTime);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function completeSpan(
        int $startTime,
        int $endTime,
        string $name,
        ?bool $success = null,
        ?string $message = null,
        ?array $attributes = null
    ): void {
        $sb = $this->startSpan($name, startTime: $startTime);
        $sb->end($success, $message, $attributes, endTime: $endTime);
    }

    public function endAllSpans(): void
    {
        while (!empty($this->spanStack)) {
            $this->endSpan(success: false, message: "Span orphaned and auto-closed");
        }
    }

    public function logSpan(Span $entry): void
    {
        $this->traceData[] = $entry;
    }
}
