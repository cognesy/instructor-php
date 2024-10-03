<?php

namespace Cognesy\Evals\LLMModes;

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\LLM\Data\LLMResponse;

class EvalRequest
{
    public function __construct(
        public string       $answer = '',
        public string|array $query = '',
        public array        $schema = [],
        public Mode         $mode = Mode::Text,
        public string       $connection = '',
        public bool         $isStreamed = false,
        public ?LLMResponse $response = null,
    ) {}
}