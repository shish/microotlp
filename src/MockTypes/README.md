A bunch of classes vaguely following the classes from the `open-telemetry/gen-otlp-protobuf` package.

The official protobuf classes have a bunch of validation, conversion, binary encoding support, etc - useful for development, but slow for production use. These mock classes are dumb containers with no validation, and can only be exported to JSON, but run 40x faster.
