<?php

namespace Cognesy\Instructor\ApiClient\Responses;

class PartialApiResponse
{
    public function __construct(
        public string $delta,
        public array  $responseData,
        public string $functionName = '',
        public string $finishReason = '',
        public string $id = '',
        public int $inputTokens = 0,
        public int $outputTokens = 0,
    ) {}

    public function toArray(): array {
        return [
            'delta' => $this->delta,
            'function_name' => $this->functionName,
            'finish_reason' => $this->finishReason,
            'response_data' => $this->responseData
        ];
    }
}