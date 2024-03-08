<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\Utils\Configuration;

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
