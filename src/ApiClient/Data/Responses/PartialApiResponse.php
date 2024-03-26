<?php

namespace Cognesy\Instructor\ApiClient\Data\Responses;

class PartialApiResponse
{
    public function __construct(
        public string $delta,
        public array  $responseData,
        public string $functionName = '',
        public string $finishReason = '',
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