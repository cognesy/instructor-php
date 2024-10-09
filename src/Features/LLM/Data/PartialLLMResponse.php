<?php

namespace Cognesy\Instructor\Features\LLM\Data;

use Cognesy\Instructor\Utils\Json\Json;

class PartialLLMResponse
{
    private mixed $value = null; // data extracted from response or tool calls
    private string $content = '';

    public function __construct(
        public string $contentDelta = '',
        public array  $responseData = [],
        public string $toolName = '',
        public string $toolArgs = '',
        public string $finishReason = '',
        public ?Usage $usage = null,
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
