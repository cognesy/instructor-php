<?php

namespace Cognesy\Evals\Evals\Data;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Data\LLMResponse;

class EvalInput
{
    public function __construct(
        public string|array        $messages = '',
        public string|array|object $schema = [],
        public Mode                $mode = Mode::Json,
        public string              $connection = '',
        public bool                $isStreamed = false,
        public ?LLMResponse        $response = null,
    ) {}

    public function withResponse(LLMResponse $response) : self {
        $this->response = $response;
        return $this;
    }
}