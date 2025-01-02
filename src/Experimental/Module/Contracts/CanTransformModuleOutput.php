<?php

namespace Cognesy\Instructor\Experimental\Module\Contracts;

interface CanTransformModuleOutput
{
    public function toOutput(array $output): mixed;
}