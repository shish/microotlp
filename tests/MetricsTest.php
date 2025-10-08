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

    public function testSync(): void
    {
        $c = new MyClient(
            targetUrl: "test://",
            traceId: "5B8EFFF798038103D269B633813FC60C",
            spanId: "EEE19B7EC3C1B173",
            resourceAttributes: [
                "service.name" => "my.service",
            ],
            scopeAttributes: [
                "my.scope.attribute" => "some scope attribute",
            ],
        );
        $c->logCounter("my.counter", 5, unit: "1", description: "I am a Counter");
        $c->logGauge("my.gauge", 10, unit: "1", description: "I am a Gauge");
        $c->logHistogram(
            "my.histogram",
            unit: "1",
            description: "I am a Histogram",
            count: 2,
            sum: 2,
            bucketCounts: [1, 1],
            explicitBounds: [1],
            min: 0,
            max: 2,
        );
        $c->logExponentialHistogram(
            "my.exponential.histogram",
            unit: "1",
            description: "I am an Exponential Histogram",
            count: 3,
            sum: 10,
            scale: 0,
            zeroCount: 1,
            positive: [
                "offset" => 1,
                "bucketCounts" => [0, 2],
            ],
            min: 0,
            max: 5,
            zeroThreshold: 0,
        );
        $c->flush();

        $ref = $c->stripTimestamps($c->getRefData('metrics'));
        $gen = $c->stripTimestamps($c->getTestData());

        // MicroOTLP name/version number are going to be different from reference data
        $ref["resourceMetrics"][0]["scopeMetrics"][0]["scope"]["name"] = '';
        $gen["resourceMetrics"][0]["scopeMetrics"][0]["scope"]["name"] = '';
        $ref["resourceMetrics"][0]["scopeMetrics"][0]["scope"]["version"] = '';
        $gen["resourceMetrics"][0]["scopeMetrics"][0]["scope"]["version"] = '';

        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["startTimeUnixNano"]);
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["attributes"]);
        unset($gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["attributes"]);
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][1]["gauge"]["dataPoints"][0]["attributes"]);
        unset($gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][1]["gauge"]["dataPoints"][0]["attributes"]);
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][2]["histogram"]["dataPoints"][0]["startTimeUnixNano"]);
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][2]["histogram"]["dataPoints"][0]["attributes"]);
        unset($gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][2]["histogram"]["dataPoints"][0]["attributes"]);
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][3]["exponentialHistogram"]["dataPoints"][0]["startTimeUnixNano"]);
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][3]["exponentialHistogram"]["dataPoints"][0]["attributes"]);
        unset($gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][3]["exponentialHistogram"]["dataPoints"][0]["attributes"]);

        self::assertEquals($ref, $gen);
    }
}
