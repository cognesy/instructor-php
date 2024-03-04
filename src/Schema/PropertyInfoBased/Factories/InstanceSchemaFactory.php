<?php

namespace Cognesy\Instructor\Schema\PropertyInfoBased\Factories;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;

class InstanceSchemaFactory
{
    public function schema(string $anyType) : Schema
    {
        return $this->makeSchema((new TypeDetailsFactory)->fromTypeName($anyType), '', '');
    }
}