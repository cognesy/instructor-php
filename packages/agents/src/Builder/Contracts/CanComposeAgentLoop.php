<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

use Cognesy\Agents\CanControlAgentLoop;

interface CanComposeAgentLoop
{
    public function withCapability(CanProvideAgentCapability $capability): self;

    public function build(): CanControlAgentLoop;
}

