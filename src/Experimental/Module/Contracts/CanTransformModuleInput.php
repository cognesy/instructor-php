<?php

namespace Cognesy\Instructor\Experimental\Module\Contracts;

interface CanTransformModuleInput
{
    public function fromInput(mixed ...$inputs): array;
}