<?php declare(strict_types=1);

namespace Cognesy\Schema\Data\Schema\Traits\Schema;

use Cognesy\Schema\Data\Schema\Schema;
use Exception;

trait ProvidesPropertyAccess
{
    /** @return Schema[] */
    public function getPropertySchemas() : array {
        return $this->properties;
    }

    /** @return string[] */
    public function getPropertyNames() : array {
        return array_keys($this->properties);
    }

    public function getPropertySchema(string $name) : Schema {
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
