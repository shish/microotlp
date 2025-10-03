<?php

declare(strict_types=1);

namespace MicroOTEL\Entries;

use MicroOTEL\Encoders;

class LogEntry extends BaseEntry
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function __construct(
        \MicroOTEL\Client $client,
        protected string $message,
        protected array $attributes = [],
    ) {
        parent::__construct($client);
    }

    /**
     * @return array<string, mixed>
     */
    public function getPacket(): array
    {
        return [
            "timeUnixNano" => (int)(microtime(true) * 1e9),
            "severityNumber" => 10,
            "severityText" => "Information",
            "traceId" => $this->client->traceId,
            "spanId" => end($this->client->spanIds) ?: '',
            "body" => ["stringValue" => $this->message],
            "attributes" => Encoders::dict2otel($this->attributes),
        ];
    }
}
