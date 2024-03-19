<?php

namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;
use Cognesy\Instructor\LLMs\Data\FunctionCall;

class StreamedFunctionCallCompleted extends Event
{
    public function __construct(
        public FunctionCall $functionCall
    ){
        parent::__construct();
    }

    public function __toString() : string {
        return json_encode($this->functionCall);
    }
}
