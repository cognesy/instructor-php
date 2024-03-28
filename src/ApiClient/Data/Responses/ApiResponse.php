<?php

namespace Cognesy\Instructor\ApiClient\Data\Responses;

class ApiResponse
{
    public function __construct(
        public string $content,
        public array  $responseData,
        public string $functionName = '',
        public string $finishReason = '',
        public string $id = '',
    ) {}

    public function toArray(): array {
        return [
            'content' => $this->content,
            'function_name' => $this->functionName,
            'finish_reason' => $this->finishReason,
            'response_data' => $this->responseData,
        ];
    }
}