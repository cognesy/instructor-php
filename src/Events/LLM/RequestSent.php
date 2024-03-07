<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;

class RequestSent extends Event
{
    public function __construct(
        public array $request = [],
    ) {
        parent::__construct();
    }

    public function __toString(): string
    {
        return json_encode($this->request);
    }
}