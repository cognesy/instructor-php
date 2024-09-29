<?php

namespace Cognesy\Instructor\Extras\LLM\Data;

use Cognesy\Instructor\Utils\Json\Json;

class ApiResponse
{
    public function __construct(
        public string $content = '',
        public array  $responseData = [],
        public string $toolName = '',
        public string $toolArgs = '',
        public array $toolsData = [],
        public string $finishReason = '',
        public ?ToolCalls $toolCalls = null,
        public int $inputTokens = 0,
        public int $outputTokens = 0,
        public int $cacheCreationTokens = 0,
        public int $cacheReadTokens = 0,
    ) {}

    public function getJson(): string {
        return Json::find($this->content);
    }

    public static function fromPartialResponses(
        array $partialResponses,
    ) : ApiResponse {
        $instance = new self();
        $content = '';
        foreach($partialResponses as $partialResponse) {
            $content .= $partialResponse->delta;
            $instance->responseData[] = $partialResponse->responseData;
            $instance->toolName = ''; // TODO: fix me
            $instance->toolArgs = ''; // TODO: fix me
            $instance->toolsData = [];
            $instance->inputTokens += $partialResponse->inputTokens;
            $instance->outputTokens += $partialResponse->outputTokens;
            $instance->cacheCreationTokens += $partialResponse->cacheCreationTokens;
            $instance->cacheReadTokens += $partialResponse->cacheReadTokens;
            $instance->finishReason = $partialResponse->finishReason;
        }
        $instance->content = $content;
        return $instance;
    }
}
