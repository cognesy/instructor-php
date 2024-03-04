<?php

namespace Cognesy\Instructor\Contracts;

interface CanValidateResponse
{
    public function validate(object $response) : bool;
    public function errors() : string;
}