MicroOTLP
=========

Because I don't want my logging framework to require a specific web serving framework @_@

MicroOTLP is a minimalistic OpenTelemetry Protocol (OTLP) client library for sending telemetry data (traces, metrics, and logs) to an OTLP collector or backend. It is designed to be lightweight and easy to integrate into various applications without the need for a full-fledged OpenTelemetry SDK.

Limitations
-----------
- Only supports HTTP transport for sending data
- Assumes one Trace per request (though you can create multiple `Client`s if you want multiple traces)
- Assumes metrics, logs, and traces are sent to the same Collector
