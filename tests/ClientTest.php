<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

class ClientTest extends \PHPUnit\Framework\TestCase
{
    public function testBasic(): void
    {
        $c = new MyClient();
        self::assertFalse($c->hasData());
    }

    public function testTime(): void
    {
        $c = new MyClient();
        $t1 = $c->time();
        usleep(1000);
        $t2 = $c->time();
        self::assertGreaterThan($t1, $t2);
    }

    public function testFromHTTPHeader(): void
    {
        $header = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';
        $_SERVER['HTTP_TRACEPARENT'] = $header;
        $c = new MyClient();
        self::assertEquals('4bf92f3577b34da6a3ce929d0e0e4736', $c->traceId);
        self::assertEquals('00f067aa0ba902b7', $c->spanId);
        // self::assertTrue($ctx->traceFlags & 1); // sampled
    }
}
