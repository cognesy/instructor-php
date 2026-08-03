<?php

declare(strict_types=1);

namespace Cognesy\Config\Contracts;

use Cognesy\Config\Secrets\ResolvedSecret;

interface CanResolveSecrets
{
    public function resolve(string $name): ?ResolvedSecret;
}
