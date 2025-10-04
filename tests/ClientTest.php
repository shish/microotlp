<?php

declare(strict_types=1);

class ClientTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic(): void
    {
        $c = new \MicroOTLP\Client("test://");
        self::assertFalse($c->hasData());
    }

    public function testLogging(): void
    {
        $c = new \MicroOTLP\Client("test://");
        $logger = $c->getLogLogger();
        $logger->log("Hello logger!");
        self::assertTrue($c->hasData());
        $c->flush();
        self::assertFalse($c->hasData());
    }

    public function testMetrics(): void
    {
        $c = new \MicroOTLP\Client("test://");
        $logger = $c->getMetricLogger();
        $logger->logCounter("my.counter", 5);
        $logger->logGauge("my.gauge", 10);
        /*
        $logger->logHistogram(
            "my.histogram",
            count: 2,
            sum: 2,
            bucketCounts: [1, 1],
            explicitBounds: [1],
            min: 0,
            max: 2,
        );
        $logger->logExponentialHistogram(
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
        $c->flush();
        self::assertFalse($c->hasData());
    }

    public function testTracing(): void
    {
        $c = new \MicroOTLP\Client("test://");
        $tracer = $c->getTraceLogger();
        $span1 = $tracer->startSpan("test-outer-span");
        usleep(100_000);
        $span2 = $tracer->startSpan("test-inner-span");
        usleep(100_000);
        $span2->end();
        usleep(100_000);
        $span1->end();
        self::assertTrue($c->hasData());
        $c->flush();
        self::assertFalse($c->hasData());
    }
}
