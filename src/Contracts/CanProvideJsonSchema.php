<?php

namespace Cognesy\Instructor\Contracts;

interface CanProvideJsonSchema
{
    public function toJsonSchema() : array;
}