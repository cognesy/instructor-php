<?php

namespace Cognesy\Instructor\Experimental\Module\Core\Traits\ModuleCall;

use InvalidArgumentException;

trait HandlesDynamicProperties
{
    public function __get(string $name) : mixed {
        return $this->get($name);
    }

    public function __set(string $name, mixed $value) : void {
        throw new InvalidArgumentException('Cannot modify ModuleCall values');
    }

    public function __isset(string $name) : bool {
        return $this->hasInput($name) || $this->hasOutput($name);
    }
}