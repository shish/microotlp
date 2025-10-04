<?php

declare(strict_types=1);

namespace MicroOTLP;

use MicroOTLP\Transports\Transport;
use MicroOTLP\Loggers\LogLogger;
use MicroOTLP\Loggers\MetricLogger;
use MicroOTLP\Loggers\TraceLogger;
use Opentelemetry\Proto\Common\V1\InstrumentationScope;
use Opentelemetry\Proto\Common\V1\AnyValue;
use Opentelemetry\Proto\Common\V1\KeyValue;
use Opentelemetry\Proto\Resource\V1\Resource;

class Client
{
    private readonly Transport $transport;
    private readonly TraceLogger $traceLogger;
    private readonly MetricLogger $metricLogger;
    private readonly LogLogger $logLogger;
    /** @var array<string, mixed> */
    private array $resourceAttributes = [];
    /** @var array<string, mixed> */
    private array $scopeAttributes = [];
    public string $traceId;
    /** @var array<string> */
    public array $spanIds = [];

    /**
     * @param array<string, mixed>|null $resourceAttributes
     * @param array<string, mixed>|null $scopeAttributes
     */
    public function __construct(
        string $url,
        ?string $traceId = null,
        ?string $spanId = null,
        ?array $resourceAttributes = null,
        ?array $scopeAttributes = null,
    ) {
        [$scheme, $path] = explode("://", $url, 2) + [1 => ''];
        $this->transport = match($scheme) {
            'http', 'https' => new Transports\HTTPTransport($url),
            'file' => new Transports\FileTransport($path),
            'test' => new Transports\TestTransport(),
            default => throw new \InvalidArgumentException("Unsupported URL scheme: {$scheme}"),
        };

        $this->traceLogger = new TraceLogger($this);
        $this->metricLogger = new MetricLogger($this);
        $this->logLogger = new LogLogger($this);
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
            "name" => "microotlp",
            "version" => "0.0.0",
            "attributes" => Encoders::dict2otel($this->scopeAttributes)
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
