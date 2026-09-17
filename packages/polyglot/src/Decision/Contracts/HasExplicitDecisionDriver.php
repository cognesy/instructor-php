<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Contracts;

interface HasExplicitDecisionDriver
{
    public function explicitDecisionDriver(): ?CanProcessDecisionRequest;
}
