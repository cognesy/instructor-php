<?php

declare(strict_types=1);

namespace Cognesy\Tell\Contracts;

use Cognesy\Tell\Contracts\Data\TellEffectiveConfiguration;
use Cognesy\Tell\TellRequest;

interface CanResolveTellConfiguration
{
    public function resolve(TellRequest $request): TellEffectiveConfiguration;
}
