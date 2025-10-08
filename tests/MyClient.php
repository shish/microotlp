<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

class MyClient extends \MicroOTLP\Client
{
    public function hasData(): bool
    {
        return $this->logData || $this->metricData || $this->traceData;
    }

    /**
     * @return array<int, mixed>
     */
    public function getLogData(): array
    {
        return \Safe\json_decode(\Safe\json_encode($this->logData));
    }

    /**
     * @return array<int, mixed>
     */
    public function getMetricData(): array
    {
        return \Safe\json_decode(\Safe\json_encode($this->metricData));
    }

    /**
     * @return array<int, mixed>
     */
    public function getTraceData(): array
    {
        return \Safe\json_decode(\Safe\json_encode($this->traceData));
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getTestData(): array
    {
        return \Safe\json_decode($this->testData, true);
    }

    /**
     * @return array<string|int, mixed>
     */
    public function getRefData(string $api): array
    {
        $path = __DIR__ . "/../output-ref/$api.json";
        if (!file_exists($path)) {
            throw new \RuntimeException("Reference data file not found: $path");
        }
        return \Safe\json_decode(\Safe\file_get_contents($path), true);
    }

    /**
     * Recursively strip out timestamp fields from a JSON object
     *
     * @param array<string|int, mixed> $array
     * @return array<string|int, mixed>
     */
    public function stripTimestamps(array $array): array
    {
        foreach ($array as $key => &$value) {
            if (is_array($value)) {
                $value = $this->stripTimestamps($value);
            } elseif (is_string($key) && str_ends_with($key, 'UnixNano')) {
                $value = '';
            }
        }
        return $array;
    }
}
