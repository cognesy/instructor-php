<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\ErrorHandling\Contracts;

use Cognesy\Agents\Core\ErrorHandling\Data\ErrorContext;

interface CanResolveErrorContext
{
    public function resolve(object $state): ErrorContext;
}
