<?php

namespace Cognesy\Instructor\Features\Schema\Data\Schema\Traits\Schema;

use Cognesy\Instructor\Features\Schema\Data\Schema\Schema;
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