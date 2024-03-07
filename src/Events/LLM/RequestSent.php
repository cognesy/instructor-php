<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;

class RequestSent extends Event
{
    public function __construct(
        public array $request = [],
    ) {}
}