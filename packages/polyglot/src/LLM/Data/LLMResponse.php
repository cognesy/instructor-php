<?php

namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Polyglot\LLM\Enums\LLMFinishReason;
use Cognesy\Polyglot\LLM\Enums\OutputMode;
use Cognesy\Utils\Json\Json;

/**
 * Represents a response from the LLM.
 */
class LLMResponse
{
    private mixed $value = null;
    private array $partialResponses = [];
    private bool $isPartial = false;

    public function __construct(
        private string     $content = '',
        private string     $finishReason = '',
        private ?ToolCalls $toolCalls = null,
        private string     $reasoningContent = '',
        private ?Usage     $usage = null,
        private array      $responseData = [],
    ) {
        $this->usage = $usage ?? new Usage();
    }

    // STATIC ////////////////////////////////////////////////

    /**
     * Create an LLMResponse from an array of PartialLLMResponses.
     *
     * @param PartialLLMResponse[] $partialResponses
     * @return LLMResponse
     */
    public static function fromPartialResponses(array $partialResponses = []) : self {
        $newResponse = new self();
        foreach ($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            $newResponse->applyPartialResponse($partialResponse);
        }
        $newResponse->toolCalls = ToolCalls::fromArray(self::makeTools($partialResponses));
        return $newResponse;
    }

    // PUBLIC ////////////////////////////////////////////////

    /**
     * Checks if the response has a processed / transformed value.
     *
     * @param mixed $value
     * @return LLMResponse
     */
    public function hasValue() : bool {
        return $this->value !== null;
    }

    /**
     * Set the processed / transformed value of the response.
     * @param mixed $value
     * @return $this
     */
    public function withValue(mixed $value) : self {
        $this->value = $value;
        return $this;
    }

    /**
     * Get the processed / transformed value of the response.
     * @return mixed
     */
    public function value() : mixed {
        return $this->value;
    }

    /**
     * Check if the response has content.
     * @return bool
     */
    public function hasContent() : bool {
        return $this->content !== '';
    }

    /**
     * Get the content of the response.
     * @return string
     */
    public function content() : string {
        return $this->content;
    }

    /**
     * Set the content of the response.
     * @param string $content
     * @return $this
     */
    public function withContent(string $content) : self {
        $this->content = $content;
        return $this;
    }

    /**
     * Set reasoning content of the response.
     * @return bool
     */
    public function withReasoningContent(string $reasoningContent) : self {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }

    /**
     * Get the reasoning content of the response.
     * @return string
     */
    public function reasoningContent() : string {
        return $this->reasoningContent;
    }

    /**
     * Check if the response has reasoning content.
     * @return bool
     */
    public function hasReasoningContent() : bool {
        return $this->reasoningContent !== '';
    }

    /**
     * Find the JSON data in the response - try checking for tool calls (if any are present)
     * or find and extract JSON from the returned content.
     *
     * @return Json
     */
    public function findJsonData(OutputMode $mode = null): Json {
        return match(true) {
            ($mode == OutputMode::Tools) && $this->hasToolCalls() => match(true) {
                $this->toolCalls->hasSingle() => Json::fromArray($this->toolCalls->first()?->args()),
                default => Json::fromArray($this->toolCalls->toArray()),
            },
            $this->hasContent() => Json::fromString($this->content),
            default => Json::fromString($this->content),
        };
    }

    public function hasToolCalls() : bool {
        return $this->toolCalls?->hasAny() ?? false;
    }

    public function usage() : Usage {
        return $this->usage ?? new Usage();
    }

    public function toolCalls() : ToolCalls {
        return $this->toolCalls ?? new ToolCalls();
    }

    public function finishReason() : LLMFinishReason {
        return LLMFinishReason::fromText($this->finishReason);
    }

    public function hasFinishReason() : bool {
        return $this->finishReason !== '';
    }

    public function responseData() : array {
        return $this->responseData;
    }

    public function toArray() : array {
        return [
            'content' => $this->content,
            'reasoningContent' => $this->reasoningContent,
            'finishReason' => $this->finishReason,
            'toolCalls' => $this->toolCalls?->toArray() ?? [],
            'usage' => $this->usage->toArray(),
            // raw response data
            'responseData' => $this->responseData,
        ];
    }

    public function clone() : self {
        return new self(
            content: $this->content,
            finishReason: $this->finishReason,
            toolCalls: $this->toolCalls?->clone(),
            reasoningContent: $this->reasoningContent,
            usage: $this->usage?->clone(),
            responseData: $this->responseData,
        );
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * Apply a partial response to the current response.
     * This will accumulate content, reasoning content, usage,
     * and response data.
     *
     * @param PartialLLMResponse $partialResponse
     */
    private function applyPartialResponse(PartialLLMResponse $partialResponse) : void {
        $this->content .= $partialResponse->contentDelta ?? '';
        $this->reasoningContent .= $partialResponse->reasoningContentDelta ?? '';
        $this->finishReason = $partialResponse->finishReason ?? $this->finishReason;
        $this->usage()->accumulate($partialResponse->usage);
        if (!empty($partialResponse->responseData)) {
            $this->responseData[] = $partialResponse->responseData;
        }
    }

    /**
     * Make a list of tool calls from the partial responses.
     *
     * @param PartialLLMResponse[] $partialResponses
     * @return array
     */
    private static function makeTools(array $partialResponses): array {
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
}

//    /**
//     * @param PartialLLMResponse[] $partialResponses
//     * @return LLMResponse
//     */
//    private function makeFromPartialResponses(array $partialResponses = []) : self {
//        if (empty($partialResponses)) {
//            return $this;
//        }
//
//        $content = '';
//        $reasoningContent = '';
//        foreach($partialResponses as $partialResponse) {
//            if ($partialResponse === null) {
//                continue;
//            }
//            $content .= $partialResponse->contentDelta;
//            $reasoningContent .= $partialResponse->reasoningContentDelta;
//            $this->responseData[] = $partialResponse->responseData;
//            $this->usage()->accumulate($partialResponse->usage);
//            $this->finishReason = $partialResponse->finishReason;
//        }
//        $this->content = $content;
//        $this->reasoningContent = $reasoningContent;
//
//        $tools = self::makeTools($partialResponses);
//        if (!empty($tools)) {
//            $this->toolCalls = ToolCalls::fromArray($tools);
//        }
//        return $this;
//    }
