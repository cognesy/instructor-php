<?php

namespace Cognesy\Instructor\Extras\LLM\Data;

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
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheCreationTokens = 0,
        public int $cacheReadTokens = 0,
    ) {}

    // STATIC ////////////////////////////////////////////////

    public static function fromPartialResponses(array $partialResponses) : LLMResponse {
        return (new self)->makeInstance($partialResponses);
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

    public function json(): string {
        if (!$this->hasContent()) {
            return '';
        }
        return Json::from($this->content)->toString();
    }

    public function hasToolCalls() : bool {
        return !empty($this->toolCalls);
    }

    // INTERNAL //////////////////////////////////////////////

    private function makeInstance(array $partialResponses = []) : self {
        if (empty($partialResponses)) {
            return $this;
        }

        $content = '';
        foreach($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            $content .= $partialResponse->delta;
            $this->responseData[] = $partialResponse->responseData;
            $this->inputTokens += $partialResponse->inputTokens;
            $this->outputTokens += $partialResponse->outputTokens;
            $this->cacheCreationTokens += $partialResponse->cacheCreationTokens;
            $this->cacheReadTokens += $partialResponse->cacheReadTokens;
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

    private function makeTools(array $partialResponses) : array {
        $tools = [];
        $currentTool = '';
        foreach($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            if (('' !== $partialResponse->toolName ?? '')
                && ($currentTool !== ($partialResponse->toolName ?? ''))) {
                $currentTool = $partialResponse->toolName ?? '';
                $tools[$currentTool] = '';
            }
            if ('' !== $currentTool) {
                if ($partialResponse->toolArgs !== '') {
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
                'arguments' => '' !== $args ? Json::parse($args) : [],
            ];
        }
        return $data;
    }
}
