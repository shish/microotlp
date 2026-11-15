<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

use PHPUnit\Framework\Attributes\DataProvider;

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

    /**
     * @return array<string, array{string}>
     */
    public static function invalidTraceparentProvider(): array
    {
        return [
            'empty' => [''],
            'garbage' => ['not-a-traceparent'],
            'too few parts' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-01'],
            'too many parts' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01-extra'],
            'short trace-id' => ['00-4bf92f3577b34da6a3ce929d0e0e47-00f067aa0ba902b7-01'],
            'short span-id' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902-01'],
            'non-hex trace-id' => ['00-4bf92f3577b34da6a3ce929d0e0e473g-00f067aa0ba902b7-01'],
            'uppercase' => ['00-4BF92F3577B34DA6A3CE929D0E0E4736-00F067AA0BA902B7-01'],
            'all-zero trace-id' => ['00-00000000000000000000000000000000-00f067aa0ba902b7-01'],
            'all-zero span-id' => ['00-4bf92f3577b34da6a3ce929d0e0e4736-0000000000000000-01'],
            'ff version' => ['ff-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01'],
        ];
    }

    #[DataProvider("invalidTraceparentProvider")]
    public function testInvalidHTTPHeaderIsIgnored(string $header): void
    {
        $_SERVER['HTTP_TRACEPARENT'] = $header;
        $c = new MyClient();
        // Falls back to a generated trace-id and the default span-id.
        self::assertMatchesRegularExpression('/^[0-9A-F]{32}$/', $c->traceId);
        self::assertEquals('0000000000000000', $c->spanId);
    }
}
