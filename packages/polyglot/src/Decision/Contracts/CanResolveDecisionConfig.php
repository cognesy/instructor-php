<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Contracts;

use Cognesy\Polyglot\Decision\Config\DecisionConfig;

interface CanResolveDecisionConfig
{
    public function resolveConfig(): DecisionConfig;
}
