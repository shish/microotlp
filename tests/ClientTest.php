<?php

declare(strict_types=1);

class ClientTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic(): void
    {
        $c = new \MicroOTEL\Client("test://");
        self::assertFalse($c->hasData());
    }

    public function testLogging(): void
    {
        $c = new \MicroOTEL\Client("test://");
        $logger = $c->getLogLogger();
        $logger->log(new \MicroOTEL\Entries\LogEntry("Hello logger!"));
        self::assertTrue($c->hasData());
        $c->flush();
        self::assertFalse($c->hasData());
    }

    public function testMetrics(): void
    {
        $c = new \MicroOTEL\Client("test://");
        $logger = $c->getMetricLogger();
        $logger->log(new \MicroOTEL\Entries\MetricEntry("test.metric", 42));
        self::assertTrue($c->hasData());
        $c->flush();
        self::assertFalse($c->hasData());
    }

    public function testTracing(): void
    {
        $c = new \MicroOTEL\Client("test://");
        $tracer = $c->getTraceLogger();
        $span1 = $tracer->startSpan("test-outer-span");
        usleep(10);
        $span2 = $tracer->startSpan("test-innser-span");
        usleep(10);
        $span2->end();
        usleep(10);
        $span1->end();
        self::assertTrue($c->hasData());
        $c->flush();
        self::assertFalse($c->hasData());
    }
}
