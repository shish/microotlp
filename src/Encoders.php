<?php

declare(strict_types=1);

namespace MicroOTLP;

use Opentelemetry\Proto\Common\V1\AnyValue;
use Opentelemetry\Proto\Common\V1\ArrayValue;
use Opentelemetry\Proto\Common\V1\KeyValue;
use Opentelemetry\Proto\Common\V1\KeyValueList;

abstract class Encoders
{
    /**
     * @param array<mixed, mixed> $in
     * @return array<KeyValue>
     * */
    public static function dict2otel(array $in): array
    {
        $out = [];
        foreach ($in as $k => $v) {
            $out[] = new KeyValue([
                "key" => (string)$k,
                "value" => static::value2otel($v)
            ]);
        }
        return $out;
    }

    public static function value2otel(mixed $v): AnyValue
    {
        if (is_bool($v)) {
            return new AnyValue(['bool_value' => $v]);
        } elseif (is_int($v)) {
            return new AnyValue(['int_value' => $v]);
        } elseif (is_float($v)) {
            return new AnyValue(['double_value' => $v]);
        } elseif (is_string($v)) {
            return new AnyValue(['string_value' => $v]);
        } elseif (is_array($v)) {
            if (array_is_list($v)) {
                return new AnyValue([
                    'array_value' => new ArrayValue([
                        'values' => array_map(fn ($x) => static::value2otel($x), $v)
                    ]),
                ]);
            } else {
                return new AnyValue([
                    'kvlist_value' => new KeyValueList([
                        'values' => static::dict2otel($v)
                    ])
                ]);
            }
        } else {
            throw new \InvalidArgumentException('Unsupported attribute value type: ' . gettype($v));
        }
    }
}
