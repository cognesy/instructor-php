<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;

class StreamedResponseReceived extends Event
{
    public function __construct(
        public array $response = [],
    ) {}
}