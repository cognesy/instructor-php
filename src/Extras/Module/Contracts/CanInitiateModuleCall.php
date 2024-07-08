<?php

namespace Cognesy\Instructor\Extras\Module\Contracts;

use Cognesy\Instructor\Extras\Module\Core\ModuleCall;

interface CanInitiateModuleCall
{
    public function __invoke(mixed ...$args): ModuleCall;
}