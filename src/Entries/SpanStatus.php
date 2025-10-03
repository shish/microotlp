<?php

declare(strict_types=1);

namespace MicroOTEL\Entries;

enum SpanStatus: int
{
    case UNSET = 0;
    case OK = 1;
    case ERROR = 2;
}
