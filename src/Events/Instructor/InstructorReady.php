<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Events\Event;

class InstructorReady extends Event
{
    public function __construct() {
        parent::__construct();
    }

    public function __toString(): string
    {
        return '';
    }
}
