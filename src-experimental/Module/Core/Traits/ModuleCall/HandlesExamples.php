<?php

namespace Cognesy\Experimental\Module\Core\Traits\ModuleCall;

use Cognesy\Instructor\Features\Core\Data\Example;

trait HandlesExamples
{
    public function asExample() : Example {
        return new Example(
            input: $this->inputs(),
            output: $this->outputs(),
        );
    }
}