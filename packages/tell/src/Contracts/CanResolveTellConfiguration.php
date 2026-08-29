<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Data\TellEffectiveConfiguration;
use Cognesy\Tell\Data\TellRequest;

interface CanResolveTellConfiguration
{
    public function resolve(TellRequest $request): TellEffectiveConfiguration;
}
