<?php

declare(strict_types=1);

namespace MicroOTEL\Transports;

class FileTransport extends Transport
{
    public function __construct(
        private string $dir,
    ) {
        if (!is_dir($dir)) {
            mkdir($dir);
        }
    }
    /**
     * @param array<string, mixed> $data
     */
    public function sendData(string $api, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT);
        if ($json === false) {
            throw new \RuntimeException('Failed to encode data as JSON');
        }
        $filename = $this->dir . '/' . $api . '.json';
        file_put_contents($filename, $json);
    }
}
