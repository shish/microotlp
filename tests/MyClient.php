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
}
