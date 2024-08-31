<?php
namespace Cognesy\Instructor\Events\Container;

use Cognesy\Instructor\Events\Event;

class NewInstanceReturned extends Event
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
