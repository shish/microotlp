<?php

declare(strict_types=1);

namespace MicroOTLP\Transports;

use Google\Protobuf\Internal\Message;

class TestTransport extends Transport
{
    /** @var array<int, array{url: string, data: Message}> */
    public array $sent_data = [];

    protected function sendData(string $api, Message $data): void
    {
        $this->sent_data[] = ['url' => $api, 'data' => $data];
    }
}
