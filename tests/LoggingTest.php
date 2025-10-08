<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

class LoggingTest extends \PHPUnit\Framework\TestCase
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
}
