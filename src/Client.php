<?php

declare(strict_types=1);

namespace MicroOTLP;

use Google\Protobuf\Internal\Message;
use Opentelemetry\Proto\Common\V1\InstrumentationScope;
use Opentelemetry\Proto\Resource\V1\Resource;
use Opentelemetry\Proto\Logs\V1\LogsData;
use Opentelemetry\Proto\Logs\V1\ResourceLogs;
use Opentelemetry\Proto\Logs\V1\ScopeLogs;
use Opentelemetry\Proto\Logs\V1\LogRecord;
use Opentelemetry\Proto\Common\V1\AnyValue;
use Opentelemetry\Proto\Metrics\V1\Gauge;
use Opentelemetry\Proto\Metrics\V1\Sum;
use Opentelemetry\Proto\Metrics\V1\Histogram;
use Opentelemetry\Proto\Metrics\V1\ExponentialHistogram;
use Opentelemetry\Proto\Metrics\V1\NumberDataPoint;
use Opentelemetry\Proto\Metrics\V1\Metric;
use Opentelemetry\Proto\Metrics\V1\MetricsData;
use Opentelemetry\Proto\Metrics\V1\ResourceMetrics;
use Opentelemetry\Proto\Metrics\V1\ScopeMetrics;
use Opentelemetry\Proto\Metrics\V1\AggregationTemporality;
use Opentelemetry\Proto\Trace\V1\ResourceSpans;
use Opentelemetry\Proto\Trace\V1\ScopeSpans;
use Opentelemetry\Proto\Trace\V1\Span;
use Opentelemetry\Proto\Trace\V1\TracesData;
use Opentelemetry\Proto\Common\V1\ArrayValue;
use Opentelemetry\Proto\Common\V1\KeyValue;
use Opentelemetry\Proto\Common\V1\KeyValueList;

class Client
{
    private ?string $transportUrl;

    /** @var array<string, mixed> */
    private array $resourceAttributes = [];
    /** @var array<string, mixed> */
    private array $scopeAttributes = [];
    public string $traceId;
    /** @var array<string> */
    public array $spanIds = [];

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
        $this->spanIds[] = $spanId ?: bin2hex(random_bytes(8));
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
            return new AnyValue(['bool_value' => $v]);
        } elseif (is_int($v)) {
            return new AnyValue(['int_value' => $v]);
        } elseif (is_float($v)) {
            return new AnyValue(['double_value' => $v]);
        } elseif (is_string($v)) {
            return new AnyValue(['string_value' => $v]);
        } elseif (is_array($v)) {
            if (array_is_list($v)) {
                return new AnyValue([
                    'array_value' => new ArrayValue([
                        'values' => array_map(fn ($x) => self::value2otel($x), $v)
                    ]),
                ]);
            } else {
                return new AnyValue([
                    'kvlist_value' => new KeyValueList([
                        'values' => self::dict2otel($v)
                    ])
                ]);
            }
        } else {
            throw new \InvalidArgumentException('Unsupported attribute value type: ' . gettype($v));
        }
    }

    ///////////////////////////////////////////////////////////////////
    // Transport and Flush
    ///////////////////////////////////////////////////////////////////

    public function flush(): void
    {
        if ($this->logData) {
            $this->sendData(
                "logs",
                new LogsData([
                    "resource_logs" => [
                        new ResourceLogs([
                            "resource" => $this->getResource(),
                            "scope_logs" => [
                                new ScopeLogs([
                                    "scope" => $this->getScope(),
                                    "log_records" => $this->logData,
                                ]),
                            ],
                        ])
                    ]
                ])
            );
            $this->logData = [];
        }
        if ($this->metricData) {
            $this->sendData(
                "metrics",
                new MetricsData([
                    "resource_metrics" => [
                        new ResourceMetrics([
                            "resource" => $this->getResource(),
                            "scope_metrics" => [
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
        if ($this->traceData) {
            $this->sendData(
                "traces",
                new TracesData([
                    "resource_spans" => [
                        new ResourceSpans([
                            "resource" => $this->getResource(),
                            "scope_spans" => [
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

    private function sendData(string $api, Message $data): void
    {
        if (!$this->transportUrl) {
            throw new \RuntimeException("Transport is not set");
        }

        [$scheme, $path] = explode("://", $this->transportUrl, 2) + [1 => ''];
        match($scheme) {
            'http', 'https' => $this->sendDataToHTTP($this->transportUrl, $api, $data),
            'file' => $this->sendDataToFile($path, $api, $data),
            default => throw new \InvalidArgumentException("Unsupported URL scheme: {$scheme}"),
        };
    }

    private function sendDataToFile(string $dir, string $api, Message $data): void
    {
        if (!is_dir($dir)) {
            mkdir($dir);
        }
        $filename = $dir . '/' . $api . '.json';
        $data = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $json = json_decode($data, true);
        $data = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($filename, $data);
    }

    private function sendDataToHTTP(string $base, string $api, Message $data): void
    {
        $json = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $ch = curl_init($base . "/v1/" . $api);
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
            "time_unix_nano" => (string)(microtime(true) * 1e9),
            "observed_time_unix_nano" => (string)(microtime(true) * 1e9),
            "severity_number" => 10, // INFO
            "severity_text" => "Information",
            "body" => new AnyValue(["string_value" => $message]),
            "attributes" => self::dict2otel($attributes),
            "trace_id" => base64_decode($this->traceId),
            "span_id" => base64_decode(end($this->spanIds) ?: "0000000000000000"),
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
                "data_points" => [
                    new NumberDataPoint([
                        "as_double" => $value,
                        "time_unix_nano" => (int) (microtime(true) * 1e9),
                        //"attributes" => $this->formatAttributes($metadata),
                    ]),
                ],
                "aggregation_temporality" => AggregationTemporality::AGGREGATION_TEMPORALITY_DELTA,
                "is_monotonic" => true,
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
                "data_points" => [
                    new NumberDataPoint([
                        "as_double" => $value,
                        "time_unix_nano" => (int) (microtime(true) * 1_000_000_000),
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
        $this->metricData[] = new Metric(array_merge([
            "name" => $name,
            "description" => $description,
            "unit" => $unit,
            "metadata" => $metadata,
        ], $metric));
    }

    ///////////////////////////////////////////////////////////////////
    // Trace Logger
    ///////////////////////////////////////////////////////////////////

    /**
     * @param array<string, mixed> $attributes
     */
    public function startSpan(string $name, array $attributes = []): SpanBuilder
    {
        return new SpanBuilder($this, $name, $attributes);
    }

    public function logSpan(Span $entry): void
    {
        $this->traceData[] = $entry;
    }
}
