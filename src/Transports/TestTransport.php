<?php

declare(strict_types=1);

namespace MicroOTEL\Transports;

class TestTransport extends Transport
{
    /** @var array<int, array{url: string, data: array<string, mixed>}> */
    public array $sent_data = [];

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(string $api, array $data): void
    {
        $this->sent_data[] = ['url' => $api, 'data' => $data];
    }
}
