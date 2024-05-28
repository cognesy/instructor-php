<?php

namespace Cognesy\Instructor\Extras\Call\Traits;

use Cognesy\Instructor\Extras\Structure\Field;

trait HandlesCallInfo
{
    public function getName(): string {
        return $this->name;
    }

    public function getDescription(): string {
        return $this->description;
    }

    public function get(string $name) {
        return $this->arguments->get($name);
    }

    /** @return string[] returns array argument names */
    public function getArgumentNames() : array {
        $arguments = [];
        foreach ($this->arguments->fields() as $field) {
            $arguments[] = $field->name();
        }
        return $arguments;
    }

    public function getField(string $name) : Field {
        return $this->arguments->field($name);
    }
}
