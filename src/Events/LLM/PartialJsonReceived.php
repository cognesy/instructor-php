<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;

class PartialJsonReceived extends Event
{
    public function __construct(
        public string $partialJson = '',
    ) {}
}
