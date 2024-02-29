<?php

namespace Cognesy\Instructor\Contracts;

interface CanValidateObject
{
    public function validate(object $object) : bool;
    public function errors() : string;
}