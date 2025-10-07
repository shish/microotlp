<?php

declare(strict_types=1);

namespace MicroOTLP\MockTypes;

class Span extends Message
{
    /**
     * @param array<string, mixed> $attributes
     */
    public function setAttributes(array $attributes): void
    {
        $this->props["attributes"] = $attributes;
    }

    public function setStatus(Status $status): void
    {
        $this->props["status"] = $status;
    }

    public function setEndTimeUnixNano(int $endTimeUnixNano): void
    {
        $this->props["endTimeUnixNano"] = $endTimeUnixNano;
    }
}
