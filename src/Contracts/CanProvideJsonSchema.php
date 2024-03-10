<?php

namespace Cognesy\Instructor\Contracts;

/**
 * Response model can provide a JSON schema for the response object
 */
interface CanProvideJsonSchema
{
    public function toJsonSchema() : array;
}