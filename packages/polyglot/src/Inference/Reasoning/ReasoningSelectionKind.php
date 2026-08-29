<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Reasoning;

enum ReasoningSelectionKind: string
{
    case Default = 'default';
    case Disabled = 'disabled';
    case Enabled = 'enabled';
    case Effort = 'effort';
    case Budget = 'budget';
    case Adaptive = 'adaptive';
}
