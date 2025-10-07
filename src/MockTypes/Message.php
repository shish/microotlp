<?php

declare(strict_types=1);

namespace MicroOTLP\MockTypes;

class Message implements \JsonSerializable
{
    /**
     * @param array<string, mixed> $props
     */
    public function __construct(protected array $props)
    {
    }

    public function jsonSerialize(): mixed
    {
        return $this->props;
    }

    public function serializeToJsonString(): string
    {
        $v = json_encode($this->props, JSON_UNESCAPED_SLASHES);
        if ($v === false) {
            throw new \RuntimeException("Failed to serialize to JSON: " . json_last_error_msg());
        }
        return $v;
    }
}
