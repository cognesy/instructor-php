<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Contracts;

interface CanTransformModuleInput
{
    public function fromInput(mixed ...$inputs): array;
}