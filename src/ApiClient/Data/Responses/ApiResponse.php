<?php

namespace Cognesy\Instructor\ApiClient\Data\Responses;

use Cognesy\Instructor\Data\ToolCalls;
use Cognesy\Instructor\Utils\Json;

class ApiResponse
{
    public function __construct(
        public string $content = '',
        public array  $responseData = [],
        public string $functionName = '',
        public string $finishReason = '',
        public ?ToolCalls $toolCalls = null,
    ) {}

    public function toArray(): array {
        return [
            'content' => $this->content,
            'function_name' => $this->functionName,
            'finish_reason' => $this->finishReason,
            'response_data' => $this->responseData,
            'tool_calls' => $this->toolCalls->all(),
        ];
    }

    public function getJson(): string {
        if (!empty($this->toolCalls) && !$this->toolCalls->empty()) {
            return $this->toolCalls->first()->args ?? '';
        }
        return Json::find($this->content);
    }
}