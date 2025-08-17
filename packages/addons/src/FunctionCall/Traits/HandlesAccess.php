<?php declare(strict_types=1);

namespace Cognesy\Addons\FunctionCall\Traits;

use Cognesy\Dynamic\Field;

trait HandlesAccess
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

    public function getArgumentInfo(string $name) : Field {
        return $this->arguments->field($name);
    }
}
