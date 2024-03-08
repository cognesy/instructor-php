<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Events\Event;

class InstructorStarted extends Event
{
    public function __construct(
        public array $config
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->config);
    }
}