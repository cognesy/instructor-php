<?php

namespace Cognesy\Instructor\Data\Contracts;

interface HasJsonSchema
{
    public function toJsonSchema() : array;
}