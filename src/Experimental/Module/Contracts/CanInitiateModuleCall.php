<?php

namespace Cognesy\Instructor\Experimental\Module\Contracts;

use Cognesy\Instructor\Experimental\Module\Core\ModuleCall;

interface CanInitiateModuleCall
{
    public function __invoke(mixed ...$args): ModuleCall;
}