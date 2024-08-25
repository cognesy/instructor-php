<?php

namespace Cognesy\Instructor\Extras\Module\Contracts;

interface CanTransformModuleOutput
{
    public function toOutput(array $output): mixed;
}