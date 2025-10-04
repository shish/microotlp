<?php

declare(strict_types=1);

namespace MicroOTLP;

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
        protected Client $client,
        string $name,
        array $attributes = [],
    ) {
        $my_id = strtoupper(bin2hex(random_bytes(8)));
        $this->span = new Span([
            "trace_id" => base64_decode($this->client->traceId),
            "span_id" => base64_decode($my_id),
            "parent_span_id" => base64_decode(end($this->client->spanIds) ?: ''),
            "name" => $name,
            "start_time_unix_nano" => (string)(int)(microtime(true) * 1e9),
            //"end_time_unix_nano" => "0",
            "kind" => Span\SpanKind::SPAN_KIND_SERVER,
            "attributes" => Client::dict2otel($attributes),
        ]);
        $this->client->spanIds[] = $my_id;
    }

    public function end(?bool $success = null): void
    {
        array_pop($this->client->spanIds);
        if ($success !== null) {
            $this->span->setStatus(new Status([
                "code" => $success ? StatusCode::STATUS_CODE_OK : StatusCode::STATUS_CODE_ERROR,
            ]));
        }
        $this->span->setEndTimeUnixNano((string)(int)(microtime(true) * 1e9));
        $this->client->logSpan($this->span);
    }
}
