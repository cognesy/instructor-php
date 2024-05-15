<?php

namespace Cognesy\Instructor\ApiClient\Responses;

use Cognesy\Instructor\Data\ToolCalls;
use Cognesy\Instructor\Utils\Json;

class ApiResponse
{
    public function __construct(
        public string $content = '',
        public array  $responseData = [],
        public string $toolName = '',
        public string $finishReason = '',
        public ?ToolCalls $toolCalls = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}

    public function getJson(): string {
        if (!empty($this->toolCalls) && !$this->toolCalls->empty()) {
            return $this->toolCalls->first()->args ?? '';
        }
        return Json::find($this->content);
    }
}