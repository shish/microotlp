<?php

declare(strict_types=1);

namespace MicroOTLP;

enum LogSeverity: int
{
    case TRACE = 1;
    case DEBUG = 5;
    case INFO = 9;
    case WARN = 13;
    case ERROR = 17;
    case FATAL = 21;
}
