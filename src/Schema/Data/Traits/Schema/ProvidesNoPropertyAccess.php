<?php

namespace Cognesy\Instructor\Schema\Data\Traits\Schema;

use Cognesy\Instructor\Schema\Data\Schema\Schema;
use Exception;

trait ProvidesNoPropertyAccess
{
    /** @return string[] */
    public function getPropertyNames() : array {
        return [];
    }

    /** @return Schema[] */
    public function getPropertySchemas() : array {
        return [];
    }

    public function getPropertySchema(string $name) : Schema {
        throw new Exception('Property not found: ' . $name);
    }

    public function hasProperty(string $name) : bool {
        return false;
    }

    public function removeProperty(string $name): void {
        throw new Exception('Property not found: ' . $name);
    }
}