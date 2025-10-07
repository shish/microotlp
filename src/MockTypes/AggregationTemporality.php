<?php

declare(strict_types=1);

namespace MicroOTLP\MockTypes;

class AggregationTemporality
{
    public const AGGREGATION_TEMPORALITY_UNSPECIFIED = 0;
    public const AGGREGATION_TEMPORALITY_DELTA = 1;
    public const AGGREGATION_TEMPORALITY_CUMULATIVE = 2;
}
