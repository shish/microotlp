<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

class TracingTest extends \PHPUnit\Framework\TestCase
{
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
        self::assertCount(2, $traces);
        $inner = $traces[0];  // inner completes first, so it goes in the logs first
        $outer = $traces[1];
        self::assertEquals("test-inner-span", $inner->name);
        self::assertEquals("test-outer-span", $outer->name);
        self::assertEquals($outer->spanId, $inner->parentSpanId);
        self::assertGreaterThan($outer->startTimeUnixNano, $inner->startTimeUnixNano);
        self::assertGreaterThan($inner->startTimeUnixNano, $inner->endTimeUnixNano);
        self::assertGreaterThan($inner->startTimeUnixNano, $outer->endTimeUnixNano);
    }

    public function testNestedEndWithChildren(): void
    {
        $c = new MyClient();
        $outer_s = $c->startSpan("test-outer-span");
        $inner_s = $c->startSpan("test-inner-span");
        $outer_s->end(withChildren: true);  // call end() on outer, it will call end() on inner too
        $inner_s->end(); // should be a no-op

        $traces = $c->getTraceData();
        self::assertCount(2, $traces);
        $inner = $traces[0];  // inner completes first, so it goes in the logs first
        $outer = $traces[1];
        // validate that our spans are correct
        self::assertEquals("test-inner-span", $inner->name);
        self::assertEquals("test-outer-span", $outer->name);
        self::assertEquals($outer->spanId, $inner->parentSpanId);
        // outer should start first and end last
        self::assertGreaterThan($outer->startTimeUnixNano, $inner->startTimeUnixNano);
        self::assertGreaterThan($inner->endTimeUnixNano, $outer->endTimeUnixNano);
    }

    public function testNestedEndWithoutChildren(): void
    {
        $c = new MyClient();
        $outer_s = $c->startSpan("test-outer-span");
        $inner_s = $c->startSpan("test-inner-span");
        $outer_s->end(withChildren: false);  // end just the outer, leave children running
        $inner_s->end();

        $traces = $c->getTraceData();
        self::assertCount(2, $traces);
        $inner = $traces[1];  // outer completes first, so it goes in the logs first
        $outer = $traces[0];
        // validate that our spans are correct
        self::assertEquals("test-inner-span", $inner->name);
        self::assertEquals("test-outer-span", $outer->name);
        self::assertEquals($outer->spanId, $inner->parentSpanId);
        // outer should start first and end first too
        self::assertGreaterThan($outer->startTimeUnixNano, $inner->startTimeUnixNano);
        self::assertGreaterThan($outer->endTimeUnixNano, $inner->endTimeUnixNano);
    }
}
