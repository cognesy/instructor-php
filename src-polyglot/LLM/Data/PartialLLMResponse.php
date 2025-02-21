<?php

namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Utils\Json\Json;

class PartialLLMResponse
{
    private mixed $value = null; // data extracted from response or tool calls
    private string $content = '';
    private string $reasoningContent = '';

    public function __construct(
        public string $contentDelta = '',
        public string $reasoningContentDelta = '',
        public string $toolId = '',
        public string $toolName = '',
        public string $toolArgs = '',
        public string $finishReason = '',
        public ?Usage $usage = null,
        public array  $responseData = [],
    ) {}

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

    public function withContent(string $content) : self {
        $this->content = $content;
        return $this;
    }

    public function content() : string {
        return $this->content;
    }

    public function reasoningContent() : string {
        return $this->reasoningContent;
    }

    public function withReasoningContent(string $reasoningContent) : self {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }

    public function hasReasoningContent() : bool {
        return $this->reasoningContent !== '';
    }

    public function json(): string {
        if (!$this->hasContent()) {
            return '';
        }
        return Json::fromPartial($this->content)->toString();
    }

    public function withFinishReason(string $finishReason) : self {
        $this->finishReason = $finishReason;
        return $this;
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
    }
}
