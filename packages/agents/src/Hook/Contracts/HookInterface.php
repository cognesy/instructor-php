<?php declare(strict_types=1);

namespace Cognesy\Agents\Hook\Contracts;

use Cognesy\Agents\Hook\Data\HookContext;

interface HookInterface
{
    public function handle(HookContext $context) : HookContext;
}