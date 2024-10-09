<?php

namespace Cognesy\Instructor\Extras\Evals\Data;

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
        public int                 $maxTokens = 512,
    ) {}

    public function withResponse(LLMResponse $response) : self {
        $this->response = $response;
        return $this;
    }

    public function evalSchema() : EvalSchema {
        if (!$this->schema instanceof EvalSchema) {
            throw new \Exception('Schema is not an instance of EvalSchema.');
        }
        return $this->schema;
    }
}