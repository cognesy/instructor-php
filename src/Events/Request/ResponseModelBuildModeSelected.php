<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Instructor\Events\Event;

class ResponseModelBuildModeSelected extends Event
{
    public function __construct(
        public string $mode
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->mode;
    }
}