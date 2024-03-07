<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;

class ResponseReceived extends Event
{
    public function __construct(
        public array $response = [],
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->response);
    }
}