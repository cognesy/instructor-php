<?php

namespace Cognesy\Instructor\Contracts;

interface CanValidateSelf
{
    public function validate(): array;
}
