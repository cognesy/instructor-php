<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Core\Request;
use Cognesy\Instructor\Events\Event;

class ResponseGenerationFailed extends Event
{
    public function __construct(
        public Request $request,
        public string $error,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return $this->error;
    }
}