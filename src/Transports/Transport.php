<?php

declare(strict_types=1);

namespace MicroOTLP\Transports;

use Google\Protobuf\Internal\Message;

abstract class Transport
{
    public function sendLogs(Message $data): void
    {
        $this->sendData('logs', $data);
    }

    public function sendMetrics(Message $data): void
    {
        $this->sendData('metrics', $data);
    }

    public function sendTraces(Message $data): void
    {
        $this->sendData('traces', $data);
    }

    abstract protected function sendData(string $api, Message $data): void;
}
