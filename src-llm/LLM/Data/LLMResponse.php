<?php

namespace Cognesy\LLM\LLM\Data;

use Cognesy\LLM\LLM\Enums\LLMFinishReason;
use Cognesy\Utils\Json\Json;

class LLMResponse
{
    private mixed $value = null;

    public function __construct(
        private string $content = '',
        private string $finishReason = '',
        private ?ToolCalls $toolCalls = null,
        private string $reasoningContent = '',
        //private array $citations = [],
        private ?Usage $usage = null,
        private array  $responseData = [],
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

    public function withContent(string $content) : self {
        $this->content = $content;
        return $this;
    }

    public function withReasoningContent(string $reasoningContent) : self {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }

    public function reasoningContent() : string {
        return $this->reasoningContent;
    }

    public function hasReasoningContent() : bool {
        return $this->reasoningContent !== '';
    }

    public function json(): Json {
        return match(true) {
            // TODO: what about tool calls?
            $this->hasContent() => Json::from($this->content),
            default => Json::none(),
        };
    }

    public function hasToolCalls() : bool {
        return !($this->toolCalls?->empty() ?? true);
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

    public function responseData() : array {
        return $this->responseData;
    }

    public function toArray() : array {
        return [
            'content' => $this->content,
            'responseData' => $this->responseData,
            'finishReason' => $this->finishReason,
            'toolCalls' => $this->toolCalls?->toArray() ?? [],
            'usage' => $this->usage->toArray(),
        ];
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
        $reasoningContent = '';
        foreach($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            $content .= $partialResponse->contentDelta;
            $reasoningContent .= $partialResponse->reasoningContentDelta;
            $this->responseData[] = $partialResponse->responseData;
            $this->usage()->accumulate($partialResponse->usage);
            $this->finishReason = $partialResponse->finishReason;
        }
        $this->content = $content;
        $this->reasoningContent = $reasoningContent;

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
