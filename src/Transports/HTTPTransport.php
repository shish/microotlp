<?php

declare(strict_types=1);

namespace MicroOTLP\Transports;

use Google\Protobuf\Internal\Message;

class HTTPTransport extends Transport
{
    public function __construct(
        private readonly string $endpoint,
    ) {
    }

    protected function sendData(string $api, Message $data): void
    {
        $json = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $ch = curl_init($this->endpoint . "/v1/" . $api);
        if ($ch === false) {
            throw new \RuntimeException('Failed to initialize cURL');
        }
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 1);
        $ret = curl_exec($ch);
        if ($ret === false) {
            $err = curl_error($ch);
            curl_close($ch);
            throw new \RuntimeException("cURL error: $err");
        }
        curl_close($ch);
    }
}
