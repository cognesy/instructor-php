<?php

namespace Cognesy\Instructor\Container\Traits;

use Cognesy\Instructor\Container\Exceptions\InvalidDependencyException;

trait PreventsCycles
{
    /** @var int[] uses to prevent dependency cycles */
    private array $trace = [];

    private function preventDependencyCycles(string $componentName) : void {
        if (!isset($this->trace[$componentName])) {
            $this->trace[$componentName] = count($this->trace) + 1;
        } else {
            $messages = [
                "Dependency cycle detected for [$componentName]",
                "TRACE:",
                print_r($this->trace, true),
                "CONFIG:",
                print_r($this->config, true),
            ];
            throw new InvalidDependencyException(implode('\n', $messages));
        }
    }
}