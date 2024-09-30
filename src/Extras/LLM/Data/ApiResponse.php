<?php

namespace Cognesy\Instructor\Extras\LLM\Data;

use Cognesy\Instructor\Utils\Json\Json;

class ApiResponse
{
    public function __construct(
        public string $content = '',
        public array  $responseData = [],
//        public string $toolName = '', // TODO: remove
//        public string $toolArgs = '', // TODO: remove
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
        $tools = [];
        $currentTool = '';
        foreach($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }

            if (('' !== $partialResponse->toolName ?? '') && ($currentTool !== $partialResponse->toolName ?? '')) {
                $currentTool = $partialResponse->toolName ?? '';
                $tools[$currentTool] = '';
            }
            if ('' !== $currentTool) {
                if ($partialResponse->toolArgs !== '') {
                    $tools[$currentTool] .= $partialResponse->toolArgs ?? '';
                }
            }

            $content .= $partialResponse->delta;
            $instance->responseData[] = $partialResponse->responseData;
//            $instance->toolName .= $partialResponse->toolName;
//            $instance->toolArgs .= $partialResponse->toolArgs;
//            $instance->toolsData = [];
            $instance->inputTokens += $partialResponse->inputTokens;
            $instance->outputTokens += $partialResponse->outputTokens;
            $instance->cacheCreationTokens += $partialResponse->cacheCreationTokens;
            $instance->cacheReadTokens += $partialResponse->cacheReadTokens;
            $instance->finishReason = $partialResponse->finishReason;
        }

        if (!empty($tools)) {
            $instance->toolsData = [];
            foreach($tools as $tool => $args) {
                $instance->toolsData[] = [
                    'name' => $tool,
                    'arguments' => Json::parse($args),
                ];
            }
            $instance->toolCalls = ToolCalls::fromArray($instance->toolsData);
        }

        $instance->content = $content;

        return $instance;
    }
}
