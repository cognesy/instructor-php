<?php

namespace Cognesy\Instructor\Extras\Agent\Traits\Tool;

use Closure;

trait HandlesExecution
{
    private Closure $function;

    public function execute(array $args = null) : mixed {
        if (empty($this->function)) {
            throw new \Exception('No function to execute');
        }
        if (empty($this->call) && empty($args)) {
            throw new \Exception('No args to execute the function with');
        }
        return $this->function->call(...($args ?? $this->call->toArgs()));
    }
}
