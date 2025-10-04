<?php

require __DIR__ . '/../vendor/autoload.php';

$cwd = getcwd();
$c = new \MicroOTLP\Client(
    url: "file://$cwd/output-gen",
    traceId: "5B8EFFF798038103D269B633813FC60C",
    spanId: "EEE19B7EC3C1B173",
    resourceAttributes: [
        "service.name" => "my.service",
    ],
    scopeAttributes: [
        "library.name" => "my.library",
        "library.version" => "1.0.0",
    ],
);

$tl = $c->getTraceLogger();
$ml = $c->getMetricLogger();
$ll = $c->getLogLogger();

$ll->log(
    "Example log record",
    [
        "string.attribute" => "some string",
        "boolean.attribute" => true,
        "int.attribute" => 10,
        "double.attribute" => 637.704,
        "array.attribute" => ["many", "values"],
        "map.attribute" => ["some.map.key" => "some value"],
    ]
);
usleep(100);

$span = $tl->startSpan("I'm a server span", ["my.span.attr" => "some value"]);
//$span->addEvent("test-event", ["key" => "value"]);
usleep(100);
//$ml->log(new \MicroOTLP\Entries\MetricEntry("test.metric", 123, ["unit" => "ms"]));
usleep(100);
usleep(100);
$span->end();

$c->flush();
