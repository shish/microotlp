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
        $logger->log(new \MicroOTLP\Entries\MetricEntry("test.metric", 42));
        self::assertTrue($c->hasData());
        $c->flush();
        self::assertFalse($c->hasData());
    }

    public function testTracing(): void
    {
        $c = new \MicroOTLP\Client("test://");
        $tracer = $c->getTraceLogger();
        $span1 = $tracer->startSpan("test-outer-span");
        usleep(100);
        $span2 = $tracer->startSpan("test-inner-span");
        usleep(100);
        $span2->end();
        usleep(100);
        $span1->end();
        self::assertTrue($c->hasData());
        $c->flush();
        self::assertFalse($c->hasData());
    }
}
