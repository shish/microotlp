<?php

declare(strict_types=1);

namespace MicroOTLP\Loggers;

use Google\Protobuf\Internal\Message;
use Opentelemetry\Proto\Logs\V1\LogsData;
use Opentelemetry\Proto\Logs\V1\ResourceLogs;
use Opentelemetry\Proto\Logs\V1\ScopeLogs;
use Opentelemetry\Proto\Logs\V1\LogRecord;
use Opentelemetry\Proto\Common\V1\AnyValue;

/**
 * @extends BaseLogger<LogRecord>
 */
class LogLogger extends BaseLogger
{
    public function getMessage(): Message
    {
        return new LogsData([
            "resource_logs" => [
                new ResourceLogs([
                    "resource" => $this->client->getResource(),
                    "scope_logs" => [
                        new ScopeLogs([
                            "scope" => $this->client->getScope(),
                            "log_records" => $this->data,
                        ]),
                    ],
                ])
            ]
        ]);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    public function log(string $message, array $attributes = []): void
    {
        $this->data[] = new LogRecord([
            "time_unix_nano" => (string)(microtime(true) * 1e9),
            "severity_number" => 10, // INFO
            "severity_text" => "Information",
            "body" => new AnyValue(["string_value" => $message]),
            "attributes" => \MicroOTLP\Encoders::dict2otel($attributes),
            "trace_id" => base64_decode($this->client->traceId),
            "span_id" => base64_decode(end($this->client->spanIds) ?: "0000000000000000"),
        ]);
    }
}
