<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Events\Event;
use Throwable;

class ErrorRaised extends Event
{
    public function __construct(
        public Throwable $e
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->e->getMessage();
    }
}