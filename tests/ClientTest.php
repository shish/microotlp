<?php

declare(strict_types=1);

class MyClient extends \MicroOTLP\Client
{
    public function hasData(): bool
    {
        return $this->logData || $this->metricData || $this->traceData;
    }
}

class ClientTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic(): void
    {
        $c = new MyClient();
        self::assertFalse($c->hasData());
    }

    public function testLogging(): void
    {
        $c = new MyClient();
        $c->logMessage("Hello logger!");
        self::assertTrue($c->hasData());
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
        usleep(100_000);
        self::assertTrue($c->hasData());
    }

    public function testTracing(): void
    {
        $c = new MyClient();
        $span1 = $c->startSpan("test-outer-span");
        usleep(100_000);
        $span2 = $c->startSpan("test-inner-span");
        usleep(100_000);
        $span2->end();
        usleep(100_000);
        $span1->end();
        self::assertTrue($c->hasData());
    }
}
