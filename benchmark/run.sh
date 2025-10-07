#!/bin/sh
export OTEL_EXPORTER_OTLP_ENDPOINT=http://localhost:4318/
export OTEL_PHP_AUTOLOAD_ENABLED=true
export OTEL_TRACES_EXPORTER=otlp
export OTEL_METRICS_EXPORTER=otlp
export OTEL_LOGS_EXPORTER=otlp
php -S localhost:8080
