<?php

namespace Cognesy\Instructor\Contracts;

use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Cognesy\Instructor\Schema\Factories\SchemaFactory;
use Cognesy\Instructor\Schema\Factories\TypeDetailsFactory;

/**
 * Response model can provide a Schema object for the response object
 */
interface CanProvideSchema
{
    public function toSchema(
        SchemaFactory $schemaFactory,
        TypeDetailsFactory $typeDetailsFactory,
    ) : Schema;
}
