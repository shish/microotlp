<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

class LogsTest extends \PHPUnit\Framework\TestCase
{
    public function testLogging(): void
    {
        $c = new MyClient();
        $c->logMessage("Hello logger!");
        $c->logMessage("Hello again!");
        self::assertTrue($c->hasData());
        $logs = $c->getLogData();
        self::assertCount(2, $logs);
        self::assertEquals("Hello logger!", $logs[0]->body->stringValue);
        self::assertEquals("Hello again!", $logs[1]->body->stringValue);
        $log1time = $logs[0]->timeUnixNano;
        $log2time = $logs[1]->timeUnixNano;
        self::assertGreaterThan($log1time, $log2time);
    }

    public function testLogInSpan(): void
    {
        $c = new MyClient();
        $span = $c->startSpan("test-span");
        $c->logMessage("Log inside span");
        $span->end();

        $spans = $c->getTraceData();
        $logs = $c->getLogData();
        self::assertCount(1, $logs);
        self::assertEquals("Log inside span", $logs[0]->body->stringValue);
        self::assertEquals($spans[0]->spanId, $logs[0]->spanId);
        self::assertEquals($c->traceId, $logs[0]->traceId);
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
        $c->logMessage(
            "Example log record",
            attributes: [
                "string.attribute" => "some string",
                "boolean.attribute" => true,
                "int.attribute" => 10,
                "double.attribute" => 637.704,
                "array.attribute" => ["many", "values"],
                "map.attribute" => ["some.map.key" => "some value"],
            ]
        );
        $c->flush();

        $ref = $c->stripTimestamps($c->getRefData('logs'));
        $gen = $c->stripTimestamps($c->getTestData());

        // MicroOTLP name/version number are going to be different from reference data
        $ref["resourceLogs"][0]["scopeLogs"][0]["scope"]["name"] = '';
        $gen["resourceLogs"][0]["scopeLogs"][0]["scope"]["name"] = '';
        $ref["resourceLogs"][0]["scopeLogs"][0]["scope"]["version"] = '';
        $gen["resourceLogs"][0]["scopeLogs"][0]["scope"]["version"] = '';

        // The example data assumes spanId = parentSpanId+1, but we use spanId = random()
        $ref["resourceLogs"][0]["scopeLogs"][0]["logRecords"][0]["spanId"] = '';
        $gen["resourceLogs"][0]["scopeLogs"][0]["logRecords"][0]["spanId"] = '';

        self::assertEquals($ref, $gen);
    }
}
