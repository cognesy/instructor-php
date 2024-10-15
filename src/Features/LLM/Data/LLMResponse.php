<?php

namespace Cognesy\Instructor\Features\LLM\Data;

use Cognesy\Instructor\Utils\Json\Json;

class LLMResponse
{
    private mixed $value = null;

    public function __construct(
        public string $content = '',
        public array  $responseData = [],
        public array $toolsData = [],
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
        return !empty($this->toolCalls);
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
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
            $this->usage()->inputTokens += $partialResponse->usage()->inputTokens;
            $this->usage()->outputTokens += $partialResponse->usage()->outputTokens;
            $this->usage()->cacheWriteTokens += $partialResponse->usage()->cacheWriteTokens;
            $this->usage()->cacheReadTokens += $partialResponse->usage()->cacheReadTokens;
            $this->usage()->reasoningTokens += $partialResponse->usage()->reasoningTokens;
            $this->finishReason = $partialResponse->finishReason;
        }
        $this->content = $content;

        $tools = $this->makeTools($partialResponses);
        if (!empty($tools)) {
            $this->toolsData = $this->makeToolsData($tools);
            $this->toolCalls = ToolCalls::fromArray($this->toolsData);
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

    private function makeToolsData(array $tools) : array {
        $data = [];
        foreach($tools as $tool => $args) {
            $data[] = [
                'name' => $tool,
                'arguments' => '' !== $args ? Json::decode($args) : [],
            ];
        }
        return $data;
    }
}
