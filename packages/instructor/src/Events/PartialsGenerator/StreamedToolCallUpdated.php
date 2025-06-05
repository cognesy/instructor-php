<?php

namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Polyglot\LLM\Data\ToolCall;
use Cognesy\Utils\Events\Event;
use Cognesy\Utils\Json\Json;

final class StreamedToolCallUpdated extends Event
{
    public function __construct(
        public ToolCall $toolCall
    ){
        parent::__construct();
    }

    public function __toString() : string
    {
        return Json::encode($this->toolCall);
    }
}
