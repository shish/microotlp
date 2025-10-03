<?php

declare(strict_types=1);

namespace MicroOTEL\Entries;

use MicroOTEL\Encoders;

class TraceEntry extends BaseEntry
{
    private string $parentSpanId;
    private string $spanId;
    private int $startTimeUnixNano;
    private int $endTimeUnixNano;
    private SpanKind $kind = SpanKind::INTERNAL;
    private SpanStatus $status = SpanStatus::UNSET;
    // private array $events = [];
    // private array $links = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        protected \MicroOTEL\Loggers\TraceLogger $logger,
        protected string $name,
        protected array $attributes = [],
        // protected int $statusCode = 0,
        // protected string $statusMessage = "",
    ) {
        $this->startTimeUnixNano = (int) (microtime(true) * 1e9);
        $this->parentSpanId = end($logger->client->spanIds) ?: "ERROR";
        $this->spanId = strtoupper(bin2hex(random_bytes(8)));
        $logger->client->spanIds[] = $this->spanId;
    }

    public function end(): void
    {
        array_pop($this->logger->client->spanIds);
        $this->endTimeUnixNano = (int) (microtime(true) * 1e9);
    }

    public function getPacket(): array
    {
        $p = [
            "traceId" => $this->logger->client->traceId,
            "spanId" => $this->spanId,
            "parentSpanId" => $this->parentSpanId,
            "name" => $this->name,
            "startTimeUnixNano" => (string)$this->startTimeUnixNano,
            "endTimeUnixNano" => (string)$this->endTimeUnixNano,
            "kind" => $this->kind->value,
            "attributes" => Encoders::dict2otel([
                "my.span.attr" => "some value"
            ]),
        ];
        if ($this->status !== SpanStatus::UNSET) {
            $p["status"] = [
                "code" => $this->status->value,
                // "message" => $this->statusMessage,
            ];
        }
        return $p;
    }
}
