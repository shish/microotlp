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
}
