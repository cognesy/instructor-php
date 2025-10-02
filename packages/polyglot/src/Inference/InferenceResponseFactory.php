<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference;

use Cognesy\Polyglot\Inference\Collections\PartialInferenceResponseList;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceResponse;

class InferenceResponseFactory
{
     public static function fromPartialResponses(PartialInferenceResponseList $partialResponses): InferenceResponse {
        $response = new InferenceResponse(isPartial: true);
        foreach ($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            $response = self::applyPartialResponse($partialResponse, $response);
        }
        $toolCalls = ToolCalls::fromArray(self::makeTools($partialResponses));
        return new InferenceResponse(
            content: $response->content(),
            finishReason: $response->finishReason()->value,
            toolCalls: $toolCalls,
            reasoningContent: $response->reasoningContent(),
            usage: $response->usage(),
            responseData: $response->responseData(),
            isPartial: $response->isPartial(),
        );
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    /**
     * Make a list of tool calls from the partial responses.
     *
     * @param PartialInferenceResponseList $partialResponses
     * @return array <string, string> tool name => args
     */
    private static function makeTools(PartialInferenceResponseList $partialResponses): array {
        $tools = [];
        $currentTool = '';
        foreach ($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            // if the tool name changes, start a new tool call
            if ($partialResponse->hasToolName()
                && ($currentTool !== ($partialResponse->toolName()))) {
                $currentTool = $partialResponse->toolName();
                $tools[$currentTool] = '';
            }
            // append the tool arguments to it
            if ('' !== $currentTool) {
                if ($partialResponse->hasToolArgs()) {
                    $tools[$currentTool] .= $partialResponse->toolArgs();
                }
            }
        }
        return $tools;
    }

        /**
     * Apply a partial response to the current response.
     * This will accumulate content, reasoning content, usage,
     * and response data.
     *
     * @param PartialInferenceResponse $partialResponse
     */
    private static function applyPartialResponse(PartialInferenceResponse $partialResponse, InferenceResponse $response): InferenceResponse {
        $newContent = $response->content() . ($partialResponse->contentDelta ?? '');
        $newReasoningContent = $response->reasoningContent() . ($partialResponse->reasoningContentDelta ?? '');
        $newUsage = $response->usage()->withAccumulated($partialResponse->usage());
        $newResponseData = match(true) {
            empty($partialResponse->responseData) => $response->responseData(),
            default => array_merge($response->responseData(), $partialResponse->responseData),
        };
        $newFinishReason = match(true) {
            !empty($partialResponse->finishReason) => $partialResponse->finishReason,
            default => $response->finishReason()->value,
        };
        // once we have a finish reason, we are no longer partial
        // yet - it does not mean streaming is finished
        // we can still receive extra chunks - e.g. with usage data
        $newIsPartial = match(true) {
            !empty($partialResponse->finishReason) => false,
            default => $response->isPartial(),
        };
        return $response->with(
            content: $newContent,
            finishReason: $newFinishReason,
            reasoningContent: $newReasoningContent,
            usage: $newUsage,
            responseData: $newResponseData,
            isPartial: $newIsPartial,
        );
    }
}