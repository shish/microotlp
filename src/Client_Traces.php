<?php

declare(strict_types=1);

namespace MicroOTLP;

use MicroOTLP\MockTypes\ResourceSpans;
use MicroOTLP\MockTypes\ScopeSpans;
use MicroOTLP\MockTypes\Span;
use MicroOTLP\MockTypes\TracesData;

trait Client_Traces
{
    use Client_Utils;

    /** @var array<Span> */
    protected array $traceData = [];

    /** @var SpanBuilder[] */
    public array $spanStack = [];

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
}
