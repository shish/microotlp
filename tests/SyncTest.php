<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

use function Safe\file_get_contents;
use function Safe\json_decode;

class SyncTest extends \PHPUnit\Framework\TestCase
{
    private string $dir;

    public function setUp(): void
    {
        // get temp dir
        $this->dir = sys_get_temp_dir() . '/microotlp_test_' . bin2hex(random_bytes(5));
        mkdir($this->dir);

        $c = new \MicroOTLP\Client(
            targetUrl: "dir://{$this->dir}",
            traceId: "5B8EFFF798038103D269B633813FC60C",
            spanId: "EEE19B7EC3C1B173",
            resourceAttributes: [
                "service.name" => "my.service",
            ],
            scopeAttributes: [
                "my.scope.attribute" => "some scope attribute",
            ],
        );

        $span = $c->startSpan("I'm a server span", ["my.span.attr" => "some value"]);
        //$span->addEvent("test-event", ["key" => "value"]);
        usleep(100_000);
        $c->logCounter("my.counter", 5, unit: "1", description: "I am a Counter");
        $c->logGauge("my.gauge", 10, unit: "1", description: "I am a Gauge");
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
        $c->logMessage(
            "Example log record",
            [
                "string.attribute" => "some string",
                "boolean.attribute" => true,
                "int.attribute" => 10,
                "double.attribute" => 637.704,
                "array.attribute" => ["many", "values"],
                "map.attribute" => ["some.map.key" => "some value"],
            ]
        );
        usleep(100_000);
        $span->end();

        $c->flush();
    }

    public function tearDown(): void
    {
        if (is_dir($this->dir)) {
            $files = glob($this->dir . '/*.jsonl');
            assert($files !== false);
            foreach ($files as $file) {
                unlink($file);
            }
            rmdir($this->dir);
        }
    }

    public function testLogs(): void
    {
        $ref = json_decode(file_get_contents(__DIR__ . '/../output-ref/logs.json'), true);
        $gen = json_decode(file_get_contents($this->dir . '/logs.jsonl'), true);

        // MicroOTLP name/version number are going to be different from reference data
        $ref["resourceLogs"][0]["scopeLogs"][0]["scope"]["name"] = '';
        $gen["resourceLogs"][0]["scopeLogs"][0]["scope"]["name"] = '';
        $ref["resourceLogs"][0]["scopeLogs"][0]["scope"]["version"] = '';
        $gen["resourceLogs"][0]["scopeLogs"][0]["scope"]["version"] = '';

        // Zero out timestamps for comparison
        $ref["resourceLogs"][0]["scopeLogs"][0]["logRecords"][0]["timeUnixNano"] = '';
        $gen["resourceLogs"][0]["scopeLogs"][0]["logRecords"][0]["timeUnixNano"] = '';
        $ref["resourceLogs"][0]["scopeLogs"][0]["logRecords"][0]["observedTimeUnixNano"] = '';
        $gen["resourceLogs"][0]["scopeLogs"][0]["logRecords"][0]["observedTimeUnixNano"] = '';

        // The example data assumes spanId = parentSpanId+1, but we use spanId = random()
        $ref["resourceLogs"][0]["scopeLogs"][0]["logRecords"][0]["spanId"] = '';
        $gen["resourceLogs"][0]["scopeLogs"][0]["logRecords"][0]["spanId"] = '';

        self::assertEquals($ref, $gen);
    }

    public function testTraces(): void
    {
        $ref = json_decode(file_get_contents(__DIR__ . '/../output-ref/traces.json'), true);
        $gen = json_decode(file_get_contents($this->dir . '/traces.jsonl'), true);

        // MicroOTLP name/version number are going to be different from reference data
        $ref["resourceSpans"][0]["scopeSpans"][0]["scope"]["name"] = '';
        $gen["resourceSpans"][0]["scopeSpans"][0]["scope"]["name"] = '';
        $ref["resourceSpans"][0]["scopeSpans"][0]["scope"]["version"] = '';
        $gen["resourceSpans"][0]["scopeSpans"][0]["scope"]["version"] = '';

        // Zero out timestamps for comparison
        $ref["resourceSpans"][0]["scopeSpans"][0]["spans"][0]["startTimeUnixNano"] = '';
        $gen["resourceSpans"][0]["scopeSpans"][0]["spans"][0]["startTimeUnixNano"] = '';
        $ref["resourceSpans"][0]["scopeSpans"][0]["spans"][0]["endTimeUnixNano"] = '';
        $gen["resourceSpans"][0]["scopeSpans"][0]["spans"][0]["endTimeUnixNano"] = '';

        // The example data assumes spanId = parentSpanId+1, but we use spanId = random()
        $ref["resourceSpans"][0]["scopeSpans"][0]["spans"][0]["spanId"] = '';
        $gen["resourceSpans"][0]["scopeSpans"][0]["spans"][0]["spanId"] = '';

        self::assertEquals($ref, $gen);
    }

    public function testMetrics(): void
    {
        $ref = json_decode(file_get_contents(__DIR__ . '/../output-ref/metrics.json'), true);
        $gen = json_decode(file_get_contents($this->dir . '/metrics.jsonl'), true);

        // MicroOTLP name/version number are going to be different from reference data
        $ref["resourceMetrics"][0]["scopeMetrics"][0]["scope"]["name"] = '';
        $gen["resourceMetrics"][0]["scopeMetrics"][0]["scope"]["name"] = '';
        $ref["resourceMetrics"][0]["scopeMetrics"][0]["scope"]["version"] = '';
        $gen["resourceMetrics"][0]["scopeMetrics"][0]["scope"]["version"] = '';

        $ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["timeUnixNano"] = '';
        $gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["timeUnixNano"] = '';
        $ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["startTimeUnixNano"] = '';
        $gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["startTimeUnixNano"] = '';
        $ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][1]["gauge"]["dataPoints"][0]["timeUnixNano"] = '';
        $gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][1]["gauge"]["dataPoints"][0]["timeUnixNano"] = '';

        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["attributes"]);
        unset($gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"]["dataPoints"][0]["attributes"]);
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][1]["gauge"]["dataPoints"][0]["attributes"]);
        unset($gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][1]["gauge"]["dataPoints"][0]["attributes"]);

        // Remove histogram and exponential histogram metrics as they are not implemented in this test
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][2]);
        unset($ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][3]);

        self::assertEquals(
            $ref["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"][0],
            $gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"][0],
            var_export($gen["resourceMetrics"][0]["scopeMetrics"][0]["metrics"][0]["sum"][0], true)
        );
        self::assertEquals($ref, $gen);
    }
}
