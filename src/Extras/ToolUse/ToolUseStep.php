<?php

namespace Cognesy\Instructor\Extras\ToolUse;

use Cognesy\Instructor\Features\LLM\Data\LLMResponse;
use Cognesy\Instructor\Features\LLM\Data\ToolCalls;
use Cognesy\Instructor\Features\LLM\Data\Usage;
use Cognesy\Instructor\Utils\Messages\Messages;

class ToolUseStep
{
    use Traits\ToolUseStep\HandlesErrors;

    private mixed $response;
    private ?ToolCalls $toolCalls;
    private ?ToolExecutions $toolExecutions;
    private ?Messages $messages;
    private ?Usage $usage;
    private ?LLMResponse $llmResponse;

    public function __construct(
        mixed          $response = null,
        ToolCalls      $toolCalls = null,
        ToolExecutions $toolExecutions = null,
        Messages       $messages = null,
        Usage          $usage = null,
        LLMResponse    $llmResponse = null,
    ) {
        $this->response = $response;
        $this->toolCalls = $toolCalls;
        $this->toolExecutions = $toolExecutions;
        $this->messages = $messages;
        $this->usage = $usage;
        $this->llmResponse = $llmResponse;
    }

    public function response() : mixed {
        return $this->response ?? null;
    }

    public function messages() : Messages {
        return $this->messages ?? new Messages();
    }

    public function toolCalls() : ToolCalls {
        return $this->toolCalls ?? new ToolCalls();
    }

    public function hasToolCalls() : bool {
        return $this->toolCalls()->count() > 0;
    }

    public function toolExecutions() : ToolExecutions {
        return $this->toolExecutions ?? new ToolExecutions();
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
    }

    public function llmResponse() : ?LLMResponse {
        return $this->llmResponse;
    }
}
