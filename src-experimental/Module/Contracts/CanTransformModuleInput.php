<?php

namespace Cognesy\Experimental\Module\Contracts;

interface CanTransformModuleInput
{
    public function fromInput(mixed ...$inputs): array;
}