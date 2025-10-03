<?php

declare(strict_types=1);

namespace MicroOTEL;

/**
 * @phpstan-type OTELScalar array{boolValue: bool}|array{intValue: int}|array{doubleValue: float}|array{stringValue: string}
 * @phpstan-type OTELArray array{values: list<mixed>}
 * @phpstan-type OTELKVList array{values: list<array{key: string, value: mixed}>}
 * @phpstan-type OTELValue OTELScalar|array{arrayValue: OTELArray}|array{kvlistValue: OTELKVList}
 * @phpstan-type OTELDict array<array{key: string, value: mixed}>
 */
abstract class Encoders
{
    /**
     * @param array<mixed, mixed> $in
     * @return OTELKVList
     * */
    public static function dict2otel(array $in): array
    {
        $out = [];
        foreach ($in as $k => $v) {
            $out[] = [
                "key" => (string)$k,
                "value" => static::value2otel($v)
            ];
        }
        return $out;
    }

    /**
     * @param mixed $v
     * @return OTELValue
     */
    public static function value2otel(mixed $v): array
    {
        if (is_bool($v)) {
            return ['boolValue' => $v];
        } elseif (is_int($v)) {
            return ['intValue' => $v];
        } elseif (is_float($v)) {
            return ['doubleValue' => $v];
        } elseif (is_string($v)) {
            return ['stringValue' => $v];
        } elseif (is_array($v)) {
            if (array_is_list($v)) {
                return ['arrayValue' => ['values' => array_map(fn ($x) => static::value2otel($x), $v)]];
            } else {
                return ['kvlistValue' =>  ['values' => static::dict2otel($v)]];
            }
        } else {
            throw new \InvalidArgumentException('Unsupported attribute value type: ' . gettype($v));
        }
    }
}
