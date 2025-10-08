<?php

declare(strict_types=1);

namespace MicroOTLP;

use MicroOTLP\MockTypes\AnyValue;
use MicroOTLP\MockTypes\LogRecord;
use MicroOTLP\MockTypes\LogsData;
use MicroOTLP\MockTypes\ResourceLogs;
use MicroOTLP\MockTypes\ScopeLogs;

trait Client_Logs
{
    use Client_Utils;

    /** @var array<LogRecord> */
    protected array $logData = [];

    /**
     * @param array<string, mixed> $attributes
     */
    public function logMessage(
        string $message,
        LogSeverity $severity = LogSeverity::INFO,
        array $attributes = [],
    ): void {
        $this->logData[] = new LogRecord([
            "timeUnixNano" => $this->time(),
            "observedTimeUnixNano" => $this->time(),
            "severityNumber" => $severity->value,
            "severityText" => $severity->name,
            "body" => new AnyValue(["stringValue" => $message]),
            "attributes" => self::dict2otel($attributes),
            "traceId" => self::encodeId($this->traceId),
            "spanId" => self::encodeId(
                $this->spanStack
                    ? end($this->spanStack)->id
                    : $this->spanId
            ),
        ]);
    }

    public function flushLogs(?string $url = null): void
    {
        if ($this->logData) {
            $this->sendData(
                $this->getTransportUrl($url),
                "logs",
                new LogsData([
                    "resourceLogs" => [
                        new ResourceLogs([
                            "resource" => $this->getResource(),
                            "scopeLogs" => [
                                new ScopeLogs([
                                    "scope" => $this->getScope(),
                                    "logRecords" => $this->logData,
                                ]),
                            ],
                        ])
                    ]
                ])
            );
            $this->logData = [];
        }
    }
}
