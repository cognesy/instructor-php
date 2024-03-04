<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;

class TypeMap
{
    private $types = [];

    public function register(string $typeName, Schema $schema)
    {
        $this->types[$typeName] = $schema;
    }

    public function get(string $typeName) : Schema
    {
        return $this->types[$typeName];
    }
}