<?php

declare(strict_types=1);

namespace MicroOTLP\Tests;

require __DIR__ . '/../vendor/autoload.php';

$cwd = getcwd();
$c = new \MicroOTLP\Client(
    targetUrl: @$argv[1] ?: "file://$cwd/output-gen/out.jsonl",
    traceId: "5B8EFFF798038103D269B633813FC60C",
    spanId: "EEE19B7EC3C1B173",
    resourceAttributes: [
        "service.name" => "my.service",
    ],
    scopeAttributes: [
        "my.scope.attribute" => "some scope attribute",
    ],
);

usleep(100_000);

$s1 = $c->startSpan("MicroOTLP Manual Test", ["my.span.attr" => "some value"]);
//$span->addEvent("test-event", ["key" => "value"]);

usleep(100_000);

$c->logCounter("my.counter", 42);
$s2 = $c->startSpan("First subspan", ["my.span.attr" => "some value"]);

usleep(100_000);

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
$c->logCounter("my.counter", 63);

usleep(100_000);

$s2->end();

usleep(20_000);

$s3 = $c->startSpan("Second Subspan", ["my.span.attr" => "some value"]);
usleep(100_000);
$c->logMessage("Another log record");
$c->logCounter("my.counter", 74, metadata: ["extra" => "attribute"]);
usleep(100_000);
$s3->end();

usleep(100_000);

$s1->end();
$c->flush();
