<?php declare(strict_types=1);

namespace Cognesy\Experimental\ModPredict\Contracts;

interface CanTransformModuleOutput
{
    public function toOutput(array $output): mixed;
}