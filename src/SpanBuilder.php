<?php

declare(strict_types=1);

namespace MicroOTLP;

use Opentelemetry\Proto\Trace\V1\Span;
use Opentelemetry\Proto\Trace\V1\Status;
use Opentelemetry\Proto\Trace\V1\Status\StatusCode;

class SpanBuilder
{
    protected Span $span;
    protected string $id;

    /**
     * @var array<string, mixed> $attributes
     */
    protected ?array $attributes = null;

    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        protected Client $client,
        string $name,
        array $attributes = [],
    ) {
        $this->id = strtoupper(bin2hex(random_bytes(8)));
        $this->attributes = $attributes;

        $this->span = new Span([
            "trace_id" => base64_decode($this->client->traceId),
            "span_id" => base64_decode($this->id),
            "parent_span_id" => base64_decode(end($this->client->spanIds) ?: ''),
            "name" => $name,
            "start_time_unix_nano" => (string)(int)(microtime(true) * 1e9),
            //"end_time_unix_nano" => "0",
            "kind" => Span\SpanKind::SPAN_KIND_SERVER,
        ]);
        $this->client->spanIds[] = $this->id;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function end(?bool $success = null, ?string $message = null, ?array $attributes = null): void
    {
        // remove my spanId from the list, even if it's in the middle
        $this->client->spanIds = array_values(array_filter(
            $this->client->spanIds,
            fn ($id) => $id !== $this->id
        ));

        $attrsToSet = $this->attributes;
        if ($attributes !== null) {
            $attrsToSet = array_merge($attrsToSet ?? [], $attributes);
        }
        if ($attrsToSet !== null) {
            $this->span->setAttributes(Client::dict2otel($attrsToSet));
        }

        if ($success !== null || $message !== null) {
            $this->span->setStatus(new Status([
                "code" => match($success) {
                    true => StatusCode::STATUS_CODE_OK,
                    false => StatusCode::STATUS_CODE_ERROR,
                    null => StatusCode::STATUS_CODE_UNSET,
                },
                "message" => $message,
            ]));
        }

        $this->span->setEndTimeUnixNano((string)(int)(microtime(true) * 1e9));
        $this->client->logSpan($this->span);
    }
}
