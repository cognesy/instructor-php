<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Contracts;

use Cognesy\Experimental\ModPredict\Core\ModuleCall;

interface CanInitiateModuleCall
{
    public function __invoke(mixed ...$args): ModuleCall;
}