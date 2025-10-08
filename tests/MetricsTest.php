<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

class MetricsTest extends \PHPUnit\Framework\TestCase
{
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
}
