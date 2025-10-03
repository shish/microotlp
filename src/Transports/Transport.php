<?php

declare(strict_types=1);

namespace MicroOTEL\Transports;

abstract class Transport
{
    /**
     * @param array<string, mixed> $data
     */
    public function sendLogs(array $data): void
    {
        $this->sendData('logs', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendMetrics(array $data): void
    {
        $this->sendData('logs', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendTraces(array $data): void
    {
        $this->sendData('logs', $data);
    }

    /**
     * @param array<string, mixed> $data
     */
    abstract public function sendData(string $api, array $data): void;
}
