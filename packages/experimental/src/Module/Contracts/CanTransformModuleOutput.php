<?php

namespace Cognesy\Experimental\Module\Contracts;

interface CanTransformModuleOutput
{
    public function toOutput(array $output): mixed;
}