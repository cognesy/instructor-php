<?php

namespace Cognesy\Schema\Data\Schema\Traits\Schema;

use Exception;

trait ProvidesPropertyAccess
{
    /** @return \Cognesy\Schema\Data\Schema\Schema[] */
    public function getPropertySchemas() : array {
        return $this->properties;
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return array_keys($this->properties);
    }

    public function getPropertySchema(string $name) : \Cognesy\Schema\Data\Schema\Schema {
        if (!$this->hasProperty($name)) {
            throw new Exception('Property not found: ' . $name);
        }
        return $this->properties[$name];
    }

    public function hasProperty(string $name) : bool {
        return isset($this->properties[$name]);
    }

    public function removeProperty(string $name): void {
        if (!$this->hasProperty($name)) {
            throw new Exception('Property not found: ' . $name);
        }
        unset($this->properties[$name]);
        unset($this->required[$name]);
    }
}
