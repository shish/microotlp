<?php

declare(strict_types=1);

class MyClient extends \MicroOTLP\Client
{
    public function hasData(): bool
    {
        return $this->logData || $this->metricData || $this->traceData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getLogData(): array
    {
        return $this->logData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getMetricData(): array
    {
        return $this->metricData;
    }

    /**
     * @return array<string, mixed>
     */
    public function getTraceData(): array
    {
        return $this->traceData;
    }
}

class ClientTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic(): void
    {
        $c = new MyClient();
        self::assertFalse($c->hasData());

        $t1 = $c->time();
        usleep(1000);
        $t2 = $c->time();
        self::assertGreaterThan($t1, $t2);
    }

    public function testLogging(): void
    {
        $c = new MyClient();
        $c->logMessage("Hello logger!");
        $c->logMessage("Hello again!");
        self::assertTrue($c->hasData());
        $logs = $c->getLogData();
        $basic = \Safe\json_decode(\Safe\json_encode($logs), associative: true);
        self::assertCount(2, $logs);
        self::assertEquals("Hello logger!", $basic[0]["body"]["stringValue"]);
        self::assertEquals("Hello again!", $basic[1]["body"]["stringValue"]);
        $log1time = $basic[0]["timeUnixNano"];
        $log2time = $basic[1]["timeUnixNano"];
        self::assertGreaterThan($log1time, $log2time);
    }

    public function testMetrics(): void
    {
        $c = new MyClient();
        $c->logCounter("my.counter", 5);
        $c->logGauge("my.gauge", 10);
        /*
        $c->logHistogram(
            "my.histogram",
            count: 2,
            sum: 2,
            bucketCounts: [1, 1],
            explicitBounds: [1],
            min: 0,
            max: 2,
        );
        $c->logExponentialHistogram(
            "my.exponential_histogram",
            count: 3,
            sum: 10,
            zeroCount: 1,
            positive: new ExponentialHistogram\DataPoint\ExponentialHistogram\Positive([
                "offset" => 1,
                "bucket_counts" => [0, 2],
            ]),
            min: 0,
            max: 5,
            zeroThreshold: 0,
        );
        */
        usleep(1000);
        self::assertTrue($c->hasData());
    }

    public function testTracing(): void
    {
        $c = new MyClient();
        $span1 = $c->startSpan("test-outer-span");
        usleep(1000);
        $span2 = $c->startSpan("test-inner-span");
        usleep(1000);
        $span2->end();
        usleep(1000);
        $span1->end();
        self::assertTrue($c->hasData());

        $traces = $c->getTraceData();
        $basic = \Safe\json_decode(\Safe\json_encode($traces), associative: true);
        self::assertCount(2, $traces);
        $inner = $basic[0];  // inner completes first, so it goes in the logs first
        $outer = $basic[1];
        self::assertEquals("test-inner-span", $inner["name"]);
        self::assertEquals("test-outer-span", $outer["name"]);
        self::assertEquals($outer["spanId"], $inner["parentSpanId"]);
        self::assertGreaterThan($outer["startTimeUnixNano"], $inner["startTimeUnixNano"]);
        self::assertGreaterThan($inner["startTimeUnixNano"], $inner["endTimeUnixNano"]);
        self::assertGreaterThan($inner["startTimeUnixNano"], $outer["endTimeUnixNano"]);
    }
}
