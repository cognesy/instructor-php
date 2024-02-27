<?php

namespace Cognesy\Instructor\Contracts;

interface CanValidate
{
    public function validate(object $object) : bool;
    public function errors() : string;
}