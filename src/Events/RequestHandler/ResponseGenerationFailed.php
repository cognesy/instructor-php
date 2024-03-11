<?php

namespace Cognesy\Instructor\Events\RequestHandler;

use Cognesy\Instructor\Core\Request;
use Cognesy\Instructor\Events\Event;

class ResponseGenerationFailed extends Event
{
    public function __construct(
        public Request $request,
        public array $errors,
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->errors);
    }
}