MicroOTLP
=========

Because I don't want my logging framework to require a specific web serving framework @_@

MicroOTLP is a minimalistic OpenTelemetry Protocol (OTLP) client library for sending telemetry data (traces, metrics, and logs) to an OTLP collector or backend. It is designed to be lightweight and easy to integrate into various applications without the need for a full-fledged OpenTelemetry SDK.

Benefits
--------
- Zero dependencies
- 40x faster than the official OpenTelemetry SDK for PHP
- No assumption for PSR-7/15/18/etc, frameworks, or logging libraries

Differences
-----------
- Simpler API -- less flexible, but covers all the common cases with much less code

Limitations
-----------
- Only supports HTTP transport for sending data
- Assumes one Trace per request (though you can create multiple `Client`s if you want multiple traces)
- Assumes metrics, logs, and traces are sent to the same Collector

Example
-------
```php
use MicroOTLP\Client;

$c = new Client('http://localhost:4318');

$topSpan = $c->startSpan('my-span');
  $childSpan1 = $c->startSpan('initialising-stuff');
    $c->logMessage("Initialising stuff");
  $childSpan1->end();
  $childSpan2 = $c->startSpan('doing-stuff');
    $c->logMessage("Doing stuff");
    $c->logCounter("number.of.things.done", 1);
    $c->logGauge("current.thing.in.progress", 42);
  $childSpan2->end();
$topSpan->end();

$c->flush();
```

Performance
-----------
This is probably an unfair comparison because I don't have the OTLP SDK native extension installed (PRs to update the benchmark setup are welcome)

A loop of 10,000 x "create span, log message, end span":

- No instrumentation: 5ms
- With MicroOTLP (php objects): 20ms
- With MicroOTLP (protobuf objects): 90ms
- With OpenTelemetry SDK: 800ms
