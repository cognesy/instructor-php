<?php
namespace Cognesy\Experimental\Module\Core\Traits\ModuleCall;

trait HandlesExecution
{
    private function execute() : mixed {
        return ($this->moduleCall)($this->inputs);
    }
}
