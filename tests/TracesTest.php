<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

class TracesTest extends \PHPUnit\Framework\TestCase
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

    public function testSetName(): void
    {
        $c = new MyClient();
        $span = $c->startSpan("initial-name");
        $span->setName("new-name");
        $span->end();

        $traces = $c->getTraceData();
        self::assertCount(1, $traces);
        self::assertEquals("new-name", $traces[0]->name);
    }

    public function testEndSpan(): void
    {
        $c = new MyClient();
        $span1 = $c->startSpan("test-outer-span");
        usleep(1000);
        $span2 = $c->startSpan("test-inner-span");
        usleep(1000);
        $c->endSpan(); // ends inner span
        usleep(1000);
        $c->endSpan(); // ends outer span
        self::assertTrue($c->hasData());

        $traces = $c->getTraceData();
        self::assertCount(2, $traces);
        $inner = $traces[0];  // inner completes first, so it goes in the logs first
        $outer = $traces[1];
        self::assertEquals("test-inner-span", $inner->name);
        self::assertEquals("test-outer-span", $outer->name);
        self::assertEquals($outer->spanId, $inner->parentSpanId);
    }

    public function testEndSpanNoSpans(): void
    {
        $c = new MyClient();
        try {
            $c->endSpan();
            self::fail("endSpan() with no spans should complain");
        } catch (\Throwable $e) {
        }
        self::assertFalse($c->hasData());
    }

    public function testEndAllSpans(): void
    {
        $c = new MyClient();
        $span1 = $c->startSpan("test-outer-span");
        usleep(1000);
        $span2 = $c->startSpan("test-inner-span");
        usleep(1000);
        $c->endAllSpans(); // ends inner and outer span
        self::assertTrue($c->hasData());

        $traces = $c->getTraceData();
        self::assertCount(2, $traces);
        $inner = $traces[0];  // inner completes first, so it goes in the logs first
        $outer = $traces[1];
        self::assertEquals("test-inner-span", $inner->name);
        self::assertEquals("test-outer-span", $outer->name);
        self::assertEquals($outer->spanId, $inner->parentSpanId);
    }

    public function testCompleteSpan(): void
    {
        $c = new MyClient();
        $span1 = $c->startSpan("test-outer-span");
        $c->completeSpan(1, 2, "test-inner-span"); // creates and ends inner span
        $span1->end();
        self::assertTrue($c->hasData());

        $traces = $c->getTraceData();
        self::assertCount(2, $traces);
        $inner = $traces[0];  // inner completes first, so it goes
        $outer = $traces[1];
        self::assertEquals("test-inner-span", $inner->name);
        self::assertEquals("test-outer-span", $outer->name);
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
        $span = $c->startSpan("I'm a server span", ["my.span.attr" => "some value"]);
        $span->end();
        $c->flush();

        $ref = $c->stripTimestamps($c->getRefData('traces'));
        $gen = $c->stripTimestamps($c->getTestData());

        // MicroOTLP name/version number are going to be different from reference data
        $ref["resourceSpans"][0]["scopeSpans"][0]["scope"]["name"] = '';
        $gen["resourceSpans"][0]["scopeSpans"][0]["scope"]["name"] = '';
        $ref["resourceSpans"][0]["scopeSpans"][0]["scope"]["version"] = '';
        $gen["resourceSpans"][0]["scopeSpans"][0]["scope"]["version"] = '';

        // The example data assumes spanId = parentSpanId+1, but we use spanId = random()
        $ref["resourceSpans"][0]["scopeSpans"][0]["spans"][0]["spanId"] = '';
        $gen["resourceSpans"][0]["scopeSpans"][0]["spans"][0]["spanId"] = '';

        self::assertEquals($ref, $gen);
    }
}
