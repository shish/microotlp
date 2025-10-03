<?php

declare(strict_types=1);

namespace MicroOTLP\Transports;

use Google\Protobuf\Internal\Message;

class FileTransport extends Transport
{
    public function __construct(
        private string $dir,
    ) {
        if (!is_dir($dir)) {
            mkdir($dir);
        }
    }

    public function sendData(string $api, Message $data): void
    {
        $filename = $this->dir . '/' . $api . '.json';
        $data = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $json = json_decode($data, true);
        $data = json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        file_put_contents($filename, $data);
    }
}
