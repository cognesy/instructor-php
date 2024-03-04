<?php

namespace Cognesy\Instructor\Contracts;

interface CanSelfValidate
{
    public function validate(): bool;
    public function errors(): string;
}
