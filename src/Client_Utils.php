<?php

declare(strict_types=1);

namespace MicroOTLP;

use MicroOTLP\MockTypes\AnyValue;
use MicroOTLP\MockTypes\ArrayValue;
use MicroOTLP\MockTypes\InstrumentationScope;
use MicroOTLP\MockTypes\KeyValue;
use MicroOTLP\MockTypes\KeyValueList;
use MicroOTLP\MockTypes\Message;
use MicroOTLP\MockTypes\Resource;

trait Client_Utils
{
    protected ?string $transportUrl;

    /** @var array<string, mixed> */
    protected array $resourceAttributes = [];
    /** @var array<string, mixed> */
    protected array $scopeAttributes = [];

    public readonly string $traceId;
    public readonly string $spanId;

    private readonly int $hrEpoch;

    protected string $testData = "";

    /**
     * @param array<string, mixed>|null $resourceAttributes
     * @param array<string, mixed>|null $scopeAttributes
     */
    public function __construct(
        ?string $targetUrl = null,
        ?string $traceId = null,
        ?string $spanId = null,
        ?array $resourceAttributes = null,
        ?array $scopeAttributes = null,
    ) {
        $this->transportUrl = $targetUrl;
        $this->resourceAttributes = $resourceAttributes ?? [
            "service.name" => "microotel-service",
            "service.instance.id" => gethostname() ?: "unknown",
        ];
        $this->scopeAttributes = $scopeAttributes ?: [];

        $traceparent = $_SERVER['HTTP_TRACEPARENT'] ?? "";
        assert(is_string($traceparent));
        $parts = explode("-", $traceparent);
        if (count($parts) === 4) {
            $traceId = $traceId ?: $parts[1];
            $spanId = $spanId ?: $parts[2];
        }
        $this->traceId = $traceId ?: strtoupper(bin2hex(random_bytes(16)));
        $this->spanId = $spanId ?: '0000000000000000';
        $this->spanStack = [];
        $this->hrEpoch = (int)(microtime(true) * 1e9) - hrtime(true);
    }

    public function getResource(): Resource
    {
        return new Resource([
            "attributes" => self::dict2otel($this->resourceAttributes),
        ]);
    }

    public function getScope(): InstrumentationScope
    {
        return new InstrumentationScope([
            "name" => "microotlp",
            "version" => "0.0.0",
            "attributes" => self::dict2otel($this->scopeAttributes)
        ]);
    }

    ///////////////////////////////////////////////////////////////////
    // Utils
    ///////////////////////////////////////////////////////////////////

    /**
     * @param array<mixed, mixed> $in
     * @return array<KeyValue>
     * */
    public static function dict2otel(array $in): array
    {
        $out = [];
        foreach ($in as $k => $v) {
            if ($v === null) {
                continue;
            }
            $out[] = new KeyValue([
                "key" => (string)$k,
                "value" => self::value2otel($v)
            ]);
        }
        return $out;
    }

    public static function value2otel(mixed $v): AnyValue
    {
        if (is_bool($v)) {
            return new AnyValue(['boolValue' => $v]);
        } elseif (is_int($v)) {
            return new AnyValue(['intValue' => $v]);
        } elseif (is_float($v)) {
            return new AnyValue(['doubleValue' => $v]);
        } elseif (is_string($v)) {
            return new AnyValue(['stringValue' => $v]);
        } elseif (is_array($v)) {
            if (array_is_list($v)) {
                return new AnyValue([
                    'arrayValue' => new ArrayValue([
                        'values' => array_map(fn ($x) => self::value2otel($x), $v)
                    ]),
                ]);
            } else {
                return new AnyValue([
                    'kvlistValue' => new KeyValueList([
                        'values' => self::dict2otel($v)
                    ])
                ]);
            }
        } elseif ($v instanceof \Stringable) {
            return new AnyValue(['stringValue' => (string)$v]);
        } else {
            throw new \InvalidArgumentException('Unsupported attribute value type: ' . gettype($v));
        }
    }

    public static function encodeId(string $id): string
    {
        // OTLP expects hex strings, but Protobuf encodes bytes as base64 --
        // if we preemptively _decode_ the hex string as if it were base64,
        // then base64'ing it will return the original hex string.
        //return base64_decode($id);

        return $id;
    }

    public function time(): int
    {
        return $this->hrEpoch + hrtime(true);
    }

    ///////////////////////////////////////////////////////////////////
    // Transport and Flush
    ///////////////////////////////////////////////////////////////////

    private function getTransportUrl(?string $url): string
    {
        if ($url !== null) {
            return $url;
        }
        if ($this->transportUrl !== null) {
            return $this->transportUrl;
        }
        throw new \RuntimeException("Transport is not set");
    }

    public function flush(?string $url = null): void
    {
        $url = $this->getTransportUrl($url);
        $this->flushLogs($url);
        $this->flushMetrics($url);
        $this->flushTraces($url);
    }

    protected function sendData(string $url, string $api, Message $data): void
    {
        if (!str_contains($url, "://")) {
            if (
                str_ends_with($url, ".json")
                || str_ends_with($url, ".jsonl")
            ) {
                $url = "file://$url";
            } else {
                $url = "dir://$url";
            }
        }

        [$scheme, $path] = explode("://", $url, 2) + [1 => ''];
        match($scheme) {
            'http', 'https' => $this->sendDataToHTTP($url, $api, $data),
            'dir' => $this->sendDataToFile("$path/$api.jsonl", $data),
            'file' => $this->sendDataToFile($path, $data),
            'test' => $this->sendDataToTest($data),
            default => throw new \InvalidArgumentException("Unsupported URL scheme: {$scheme}"),
        };
    }

    private function sendDataToFile(string $filename, Message $data): void
    {
        // $json = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to serialize to JSON: " . json_last_error_msg());
        }
        file_put_contents($filename, "$json\n", FILE_APPEND | LOCK_EX);
    }

    private function sendDataToHTTP(string $base, string $api, Message $data): void
    {
        // $json = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to serialize to JSON: " . json_last_error_msg());
        }
        $ch = curl_init("$base/v1/$api");
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
        # $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        # $dbg = "$json\n\n$code\n\n$ret";
        # file_put_contents(tempnam(sys_get_temp_dir(), "microotlp-".time()."-$api-"), $dbg);
        curl_close($ch);
    }

    private function sendDataToTest(Message $data): void
    {
        // $json = $data->serializeToJsonString(\Google\Protobuf\PrintOptions::ALWAYS_PRINT_ENUMS_AS_INTS);
        $json = json_encode($data, JSON_UNESCAPED_SLASHES);
        if ($json === false) {
            throw new \RuntimeException("Failed to serialize to JSON: " . json_last_error_msg());
        }
        $this->testData .= "$json\n";
    }
}
