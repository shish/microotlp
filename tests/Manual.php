<?php

require __DIR__ . '/../vendor/autoload.php';

//$tt = new \MicroOTEL\Transports\HTTPTransport("https://otelcol.shish.io/");
$tt = new \MicroOTEL\Transports\FileTransport("./refs-dir");
$c = new \MicroOTEL\Client(
    transport: $tt,
    traceId: "5B8EFFF798038103D269B633813FC60C",
    spanId: "EEE19B7EC3C1B173",
    resourceAttributes: [
        "service.name" => "my.service",
    ]
);

$tl = $c->getTraceLogger();
$ml = $c->getMetricLogger();
$ll = $c->getLogLogger();

$span = $tl->startSpan("I'm a server span", ["my.span.attr" => "some value"]);
usleep(100);
//$span->addEvent("test-event", ["key" => "value"]);
usleep(100);
//$ml->log(new \MicroOTEL\Entries\MetricEntry("test.metric", 123, ["unit" => "ms"]));
usleep(100);
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
$span->end();

$c->flush();
