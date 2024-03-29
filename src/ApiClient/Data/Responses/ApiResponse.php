<?php

namespace Cognesy\Instructor\ApiClient\Data\Responses;

use Cognesy\Instructor\Data\ToolCall;
use Cognesy\Instructor\Utils\Json;

class ApiResponse
{
    public function __construct(
        public string $content = '',
        public array  $responseData = [],
        public string $functionName = '',
        public string $finishReason = '',
        /** @var ToolCall[] */
        public array  $toolCalls = [],
    ) {}

    public function toArray(): array {
        return [
            'content' => $this->content,
            'function_name' => $this->functionName,
            'finish_reason' => $this->finishReason,
            'response_data' => $this->responseData,
        ];
    }

    public function getJson(): string {
        if (!empty($this->toolCalls)) {
            return $this->toolCalls[0]->args;
        }
        return Json::find($this->content);
    }
}