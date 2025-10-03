<?php

declare(strict_types=1);

namespace MicroOTEL\Loggers;

use Opentelemetry\Proto\Trace\V1\Span;
use Opentelemetry\Proto\Trace\V1\Status;
use Opentelemetry\Proto\Trace\V1\Status\StatusCode;

class SpanBuilder
{
    protected Span $span;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        protected TraceLogger $logger,
        string $name,
        array $attributes = [],
    ) {
        $this->span = new Span([
            "trace_id" => $this->logger->client->traceId,
            "span_id" => bin2hex(random_bytes(8)),
            "parent_span_id" => end($this->logger->client->spanIds) ?: '',
            "name" => $name,
            "start_time_unix_nano" => (string)(int)(microtime(true) * 1e9),
            //"end_time_unix_nano" => "0",
            "kind" => Span\SpanKind::SPAN_KIND_SERVER,
            "attributes" => \MicroOTEL\Encoders::dict2otel($attributes),
        ]);
    }

    public function end(?bool $success = null): void
    {
        if ($success !== null) {
            $this->span->setStatus(new Status([
                "code" => $success ? StatusCode::STATUS_CODE_OK : StatusCode::STATUS_CODE_ERROR,
            ]));
        }
        $this->span->setEndTimeUnixNano((string)(int)(microtime(true) * 1e9));
        $this->logger->log($this->span);
    }
}
