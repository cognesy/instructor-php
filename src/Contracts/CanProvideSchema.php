<?php

namespace Cognesy\Instructor\Contracts;

interface CanProvideSchema
{
    public function toJsonSchema() : array;
}