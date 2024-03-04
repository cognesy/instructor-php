<?php

namespace Cognesy\Instructor;

use Cognesy\Instructor\Schema\PropertyInfoBased\Data\Schema\Schema;

class PropertyMap
{
    private $map = [];

    public function register(string $class, string $property, Schema $schema) {
        $this->map[$class][$property] = $schema;
    }

    public function get(string $class, string $property) : Schema {
        return $this->map[$class][$property];
    }

    public function has(string $class, string $property) : bool {
        return isset($this->map[$class][$property]);
    }
}