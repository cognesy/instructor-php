<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Configuration\Configuration;
use Cognesy\Instructor\Events\Event;

class InstructorReady extends Event
{
    public function __construct(
        public Configuration $config
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->config);
    }
}
