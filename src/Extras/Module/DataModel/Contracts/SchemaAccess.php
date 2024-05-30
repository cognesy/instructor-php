<?php

namespace Cognesy\Instructor\Extras\Module\DataModel\Contracts;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

interface SchemaAccess
{
    /** @return string[] */
    public function getPropertyNames(): array;

    /** @return Schema[] */
    public function getPropertySchemas(): array;

    public function getPropertySchema(string $name) : Schema;

    public function toSchema() : Schema;
}