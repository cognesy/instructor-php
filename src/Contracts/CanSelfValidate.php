<?php

namespace Cognesy\Instructor\Contracts;

interface CanSelfValidate
{
    public function validate(): array;
}
