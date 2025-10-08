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
}
