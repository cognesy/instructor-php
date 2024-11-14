<?php

namespace Cognesy\Instructor\Features\LLM\Data;

use Cognesy\Instructor\Features\LLM\Enums\LLMFinishReason;
use Cognesy\Instructor\Utils\Json\Json;

class LLMResponse
{
    private mixed $value = null;

    public function __construct(
        public string $content = '',
        public array  $responseData = [],
        public string $finishReason = '',
        public ?ToolCalls $toolCalls = null,
        public ?Usage $usage = null,
    ) {
        $this->usage = $usage ?? new Usage();
    }

    // STATIC ////////////////////////////////////////////////

    public static function fromPartialResponses(array $partialResponses) : LLMResponse {
        return (new self)->makeFromPartialResponses($partialResponses);
    }

    // PUBLIC ////////////////////////////////////////////////

    public function hasValue() : bool {
        return $this->value !== null;
    }

    public function withValue(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    public function value() : mixed {
        return $this->value;
    }

    public function hasContent() : bool {
        return $this->content !== '';
    }

    public function content() : string {
        return $this->content;
    }

    public function json(): Json {
        return match(true) {
            // TODO: what about tool calls?
            $this->hasContent() => Json::from($this->content),
            default => Json::none(),
        };
    }

    public function hasToolCalls() : bool {
        return !$this->toolCalls?->empty();
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
    }

    public function toolCalls() : ToolCalls {
        return $this->toolCalls ?? new ToolCalls();
    }

    public function finishReason() : LLMFinishReason {
        return LLMFinishReason::fromText($this->finishReason);
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * @param PartialLLMResponse[] $partialResponses
     * @return LLMResponse
     */
    private function makeFromPartialResponses(array $partialResponses = []) : self {
        if (empty($partialResponses)) {
            return $this;
        }

        $content = '';
        foreach($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            $content .= $partialResponse->contentDelta;
            $this->responseData[] = $partialResponse->responseData;
            $this->usage()->accumulate($partialResponse->usage);
            $this->finishReason = $partialResponse->finishReason;
        }
        $this->content = $content;

        $tools = $this->makeTools($partialResponses);
        if (!empty($tools)) {
            $this->toolCalls = ToolCalls::fromArray($tools);
        }
        return $this;
    }

    private function makeTools(array $partialResponses): array {
        $tools = [];
        $currentTool = '';
        foreach ($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            if (('' !== ($partialResponse->toolName ?? ''))
                && ($currentTool !== ($partialResponse->toolName ?? ''))) {
                $currentTool = $partialResponse->toolName ?? '';
                $tools[$currentTool] = '';
            }
            if ('' !== $currentTool) {
                if (('' !== ($partialResponse->toolArgs ?? ''))) {
                    $tools[$currentTool] .= $partialResponse->toolArgs ?? '';
                }
            }
        }
        return $tools;
    }
}
