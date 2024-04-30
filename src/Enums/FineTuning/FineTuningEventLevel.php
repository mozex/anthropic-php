<?php

namespace Anthropic\Enums\FineTuning;

enum FineTuningEventLevel: string
{
    case Info = 'info';
    case Warning = 'warn';
    case Error = 'error';
}
