<?php

namespace Cognesy\Instructor\ApiClient\Responses;

class PartialApiResponse
{
    public function __construct(
        public string $delta,
        public array  $responseData,
        public string $toolName = '',
        public string $finishReason = '',
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}

    public function toArray(): array {
        return [
            'delta' => $this->delta,
            'tool_name' => $this->toolName,
            'finish_reason' => $this->finishReason,
            'response_data' => $this->responseData
        ];
    }
}