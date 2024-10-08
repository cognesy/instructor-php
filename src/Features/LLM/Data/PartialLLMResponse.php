<?php

namespace Cognesy\Instructor\Features\LLM\Data;

use Cognesy\Instructor\Utils\Json\Json;

class PartialLLMResponse
{
    private mixed $data = null; // data extracted from response or tool calls
    private string $content = '';

    public function __construct(
        public string $contentDelta = '',
        public array  $responseData = [],
        public string $toolName = '',
        public string $toolArgs = '',
        public string $finishReason = '',
        public int    $inputTokens = 0,
        public int    $outputTokens = 0,
        public int    $cacheCreationTokens = 0,
        public int    $cacheReadTokens = 0,
    ) {}

    // PUBLIC ////////////////////////////////////////////////

    public function hasData() : bool {
        return $this->data !== null;
    }

    public function withData(mixed $value) : self {
        $this->data = $value;
        return $this;
    }

    public function data() : mixed {
        return $this->data;
    }

    public function hasContent() : bool {
        return $this->content !== '';
    }

    public function withContent(string $content) : self {
        $this->content = $content;
        return $this;
    }

    public function withFinishReason(string $finishReason) : self {
        $this->finishReason = $finishReason;
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
}
