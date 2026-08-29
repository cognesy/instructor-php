<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

enum ReasoningDefaultBehavior: string
{
    case Unknown = 'unknown';
    case Disabled = 'disabled';
    case Enabled = 'enabled';
    case Adaptive = 'adaptive';
    case Mandatory = 'mandatory';
}
