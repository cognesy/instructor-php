<?php

namespace Cognesy\Experimental\Module\Contracts;

use Cognesy\Experimental\Module\Core\ModuleCall;

interface CanInitiateModuleCall
{
    public function __invoke(mixed ...$args): ModuleCall;
}