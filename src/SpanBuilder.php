<?php

declare(strict_types=1);

namespace MicroOTLP;

/*
use Opentelemetry\Proto\Trace\V1\Span;
use Opentelemetry\Proto\Trace\V1\Span\SpanKind;
use Opentelemetry\Proto\Trace\V1\Status;
use Opentelemetry\Proto\Trace\V1\Status\StatusCode;
*/

use MicroOTLP\MockTypes\Span;
use MicroOTLP\MockTypes\SpanKind;
use MicroOTLP\MockTypes\Status;
use MicroOTLP\MockTypes\StatusCode;

class SpanBuilder
{
    public readonly Span $span;
    public readonly string $id;

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
        ?int $startTime = null,
    ) {
        $this->id = strtoupper(bin2hex(random_bytes(8)));
        $this->attributes = $attributes;

        $this->span = new Span([
            "traceId" => Client::encodeId($this->client->traceId),
            "spanId" => Client::encodeId($this->id),
            "parentSpanId" => Client::encodeId(
                $this->client->spanStack
                    ? end($this->client->spanStack)->id
                    : $this->client->spanId
            ),
            "name" => $name,
            "startTimeUnixNano" => $startTime ?? $this->client->time(),
            "kind" => $this->client->spanStack
                ? SpanKind::SPAN_KIND_INTERNAL
                : SpanKind::SPAN_KIND_SERVER,
        ]);
        $this->client->spanStack[] = $this;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function end(
        ?bool $success = null,
        ?string $message = null,
        ?array $attributes = null,
        ?int $endTime = null,
    ): void {
        // remove my spanId from the list, even if it's in the middle
        $this->client->spanStack = array_values(array_filter(
            $this->client->spanStack,
            fn ($sb) => $sb !== $this
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

        $this->span->setEndTimeUnixNano($endTime ?? $this->client->time());
        $this->client->logSpan($this->span);
    }
}
