<?php

namespace Cognesy\Instructor\Extras\Module\Contracts;

interface CanTransformModuleInput
{
    public function fromInput(mixed ...$inputs): array;
}