<?php

namespace Cognesy\Instructor\Extras\Structure\Traits;

use Cognesy\Instructor\Extras\Structure\Field;

trait HandlesFieldAccess
{
    /** @var Field[] */
    protected array $fields = [];

    public function has(string $field) : bool {
        return isset($this->fields[$field]);
    }

    public function field(string $name) : Field {
        if (!$this->has($name)) {
            throw new \Exception("Field `$name` not found in structure.");
        }
        return $this->fields[$name];
    }

    public function fields() : array {
        return $this->fields;
    }

    public function get(string $field) : mixed {
        return $this->field($field)->get();
    }

    public function set(string $field, mixed $value) {
        $this->field($field)->set($value);
    }

    public function __get(string $field) : mixed {
        return $this->get($field);
    }

    public function __set(string $field, mixed $value) {
        $this->set($field, $value);
    }

    public function __isset(string $field) : bool {
        return $this->has($field);
    }
}