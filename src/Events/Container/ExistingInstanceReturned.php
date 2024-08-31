<?php

namespace Cognesy\Instructor\Events\Container;

use Cognesy\Instructor\Events\Event;

class ExistingInstanceReturned extends Event
{
    public function __construct(
        public string $name
    ) {
        parent::__construct();
    }

    public function __toString() : string {
        return $this->name;
    }
}
