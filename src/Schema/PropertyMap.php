<?php
namespace Cognesy\Instructor\Schema;

use Cognesy\Instructor\Schema\Data\Schema\Schema;

/**
 * PropertyMap contains mapping of class properties to their respective schemas
 */
class PropertyMap
{
    /** @var Schema[][] schema mapping for class properties - first index is class name, second is property name */
    private $map = [];

    /**
     * Register a schema for a class property
     */
    public function register(string $class, string $property, Schema $schema) : void {
        $this->map[$class][$property] = $schema;
    }

    /**
     * Get the schema for a class property
     */
    public function get(string $class, string $property) : Data\Schema\Schema {
        return $this->map[$class][$property];
    }

    /**
     * Check if a class property has a schema mapping
     */
    public function has(string $class, string $property) : bool {
        return isset($this->map[$class][$property]);
    }
}
