<?php

declare(strict_types=1);

namespace MicroOTEL;

use MicroOTEL\Transports\Transport;
use MicroOTEL\Loggers\LogLogger;
use MicroOTEL\Loggers\MetricLogger;
use MicroOTEL\Loggers\TraceLogger;
use Opentelemetry\Proto\Common\V1\InstrumentationScope;
use Opentelemetry\Proto\Common\V1\AnyValue;
use Opentelemetry\Proto\Common\V1\KeyValue;
use Opentelemetry\Proto\Resource\V1\Resource;

class Client
{
    private TraceLogger $traceLogger;
    private MetricLogger $metricLogger;
    private LogLogger $logLogger;
    /** @var array<string, mixed> */
    private array $resourceAttributes = [];
    public string $traceId;
    /** @var array<string> */
    public array $spanIds = [];

    /**
     * @param array<string, mixed>|null $resourceAttributes
     */
    public function __construct(
        private readonly Transport $transport,
        string $traceId = '',
        string $spanId = '',
        ?array $resourceAttributes = null,
    ) {
        $this->traceLogger = new TraceLogger($this);
        $this->metricLogger = new MetricLogger($this);
        $this->logLogger = new LogLogger($this);
        $this->resourceAttributes = $resourceAttributes ?? [
            "service.name" => "microotel-service",
            "service.instance.id" => gethostname() ?: "unknown",
        ];
        $this->traceId = $traceId ?: bin2hex(random_bytes(16));
        $this->spanIds[] = $spanId ?: bin2hex(random_bytes(8));
    }

    public function getTraceLogger(): TraceLogger
    {
        return $this->traceLogger;
    }

    public function getMetricLogger(): MetricLogger
    {
        return $this->metricLogger;
    }

    public function getLogLogger(): LogLogger
    {
        return $this->logLogger;
    }

    public function getResource(): Resource
    {
        return new Resource([
            "attributes" => Encoders::dict2otel($this->resourceAttributes),
        ]);
    }

    public function getScope(): InstrumentationScope
    {
        return new InstrumentationScope([
            "name" => "my.library",
            "version" => "1.0.0",
            "attributes" => [
                new KeyValue([
                    "key" => "my.scope.attribute",
                    "value" => new AnyValue([
                        "string_value" => "some scope attribute",
                    ]),
                ]),
            ]
        ]);
    }

    public function hasData(): bool
    {
        return $this->traceLogger->hasData() || $this->metricLogger->hasData() || $this->logLogger->hasData();
    }

    public function flush(): void
    {
        if ($this->traceLogger->hasData()) {
            $this->transport->sendTraces($this->traceLogger->getMessage());
            $this->traceLogger->clear();
        }
        if ($this->metricLogger->hasData()) {
            $this->transport->sendMetrics($this->metricLogger->getMessage());
            $this->metricLogger->clear();
        }
        if ($this->logLogger->hasData()) {
            $this->transport->sendLogs($this->logLogger->getMessage());
            $this->logLogger->clear();
        }
    }
}
