<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;

class SchemaMap
{
    private $schemas = [];

    public function register(string $typeName, Schema $schema)
    {
        $this->schemas[$typeName] = $schema;
    }

    public function get(string $typeName) : Schema
    {
        return $this->schemas[$typeName];
    }

    public function has(string $typeName) : bool
    {
        return isset($this->schemas[$typeName]);
    }
}