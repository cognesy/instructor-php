<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Contracts;

use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;

interface CanProcessDecisionRequest
{
    public function handle(DecisionRequest $request): DecisionResponse;
}
