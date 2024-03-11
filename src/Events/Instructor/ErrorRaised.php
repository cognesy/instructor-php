<?php

namespace Cognesy\Instructor\Events\Instructor;

use Cognesy\Instructor\Core\Data\Request;
use Cognesy\Instructor\Events\Event;
use Throwable;

class ErrorRaised extends Event
{
    public function __construct(
        public Throwable $error,
        public ?Request $request = null,
        public mixed $context = null,
    )
    {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->error->getMessage();
    }
}
