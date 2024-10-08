<?php

namespace Cognesy\Instructor\Features\Schema;

use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;

/**
 * SchemaMap contains mapping of type names to their respective schemas
 */
class SchemaMap
{
    /** @var Schema[] schema mapping for types */
    private array $schemas = [];

    /**
     * Register a schema for a type
     */
    public function register(string $typeName, Schema $schema) : void {
        $this->schemas[$typeName] = $schema;
    }

    /**
     * Get the schema for a type
     */
    public function get(string $typeName) : Schema {
        return $this->schemas[$typeName];
    }

    /**
     * Check if a type has a schema mapping
     */
    public function has(string $typeName) : bool {
        return isset($this->schemas[$typeName]);
    }
}