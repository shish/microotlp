<?php

declare(strict_types=1);

namespace MicroOTEL\Loggers;

use Google\Protobuf\Internal\Message;
use Opentelemetry\Proto\Trace\V1\ResourceSpans;
use Opentelemetry\Proto\Trace\V1\ScopeSpans;
use Opentelemetry\Proto\Trace\V1\Span;
use Opentelemetry\Proto\Trace\V1\TracesData;

/**
 * @extends BaseLogger<Span>
 */
class TraceLogger extends BaseLogger
{
    public function getMessage(): Message
    {
        return new TracesData([
            "resource_spans" => [
                new ResourceSpans([
                    "resource" => $this->client->getResource(),
                    "scope_spans" => [
                        new ScopeSpans([
                            "scope" => $this->client->getScope(),
                            "spans" => $this->data,
                        ]),
                    ],
                ])
            ]
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function startSpan(string $name, array $attributes): SpanBuilder
    {
        return new SpanBuilder($this, $name, $attributes);
    }

    public function log(Span $entry): void
    {
        $this->data[] = $entry;
    }
}
