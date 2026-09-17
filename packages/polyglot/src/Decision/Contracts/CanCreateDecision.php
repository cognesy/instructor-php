<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Contracts;

use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\PendingDecision;

interface CanCreateDecision
{
    public function create(DecisionRequest $request): PendingDecision;
}
