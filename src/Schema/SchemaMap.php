<?php

namespace Cognesy\Instructor\Schema;

class SchemaMap
{
    private $schemas = [];

    public function register(string $typeName, Data\Schema\Schema $schema)
    {
        $this->schemas[$typeName] = $schema;
    }

    public function get(string $typeName) : Data\Schema\Schema
    {
        return $this->schemas[$typeName];
    }

    public function has(string $typeName) : bool
    {
        return isset($this->schemas[$typeName]);
    }
}