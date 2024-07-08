<?php

namespace Cognesy\Instructor\Extras\Module\Core\Traits\ModuleCall;

use Cognesy\Instructor\Data\Example;

trait HandlesExamples
{
    public function asExample() : Example {
        return new Example(
            input: $this->inputs(),
            output: $this->outputs(),
        );
    }
}