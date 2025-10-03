<?php

declare(strict_types=1);

namespace MicroOTEL\Transports;

class HTTPTransport extends Transport
{
    public function __construct(
        private readonly string $endpoint,
    ) {
    }

    /**
     * @param array<string, mixed> $data
     */
    public function sendData(string $api, array $data): void
    {
        $json = json_encode($data);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode data as JSON');
        }
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
