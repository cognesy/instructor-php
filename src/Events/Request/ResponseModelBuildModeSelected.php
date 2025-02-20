<?php

namespace Cognesy\Instructor\Events\Request;

use Cognesy\Utils\Events\Event;

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