<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

/**
 * Response model can provide a JSON schema for the response object
 */
interface CanProvideJsonSchema
{
    public function toJsonSchema(SchemaFactory $schemaFactory, TypeDetailsFactory $typeDetailsFactory) : array;
}