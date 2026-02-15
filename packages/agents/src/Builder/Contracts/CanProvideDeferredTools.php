<?php declare(strict_types=1);

namespace Cognesy\Agents\Builder\Contracts;

use Cognesy\Agents\Builder\Data\DeferredToolContext;
use Cognesy\Agents\Collections\Tools;

interface CanProvideDeferredTools
{
    public function provideTools(DeferredToolContext $context): Tools;
}

