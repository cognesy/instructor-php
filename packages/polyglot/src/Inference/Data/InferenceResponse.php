<?php

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Json\Json;

/**
 * Represents a response from the LLM.
 */
class InferenceResponse
{
    private mixed $value = null;

    private string $content;
    private string $reasoningContent;
    private string $finishReason;
    private ToolCalls $toolCalls;
    private Usage $usage;
    private array $responseData;
    private bool $isPartial;
    private array $partialResponses;

    public function __construct(
        string     $content = '',
        string     $finishReason = '',
        ?ToolCalls $toolCalls = null,
        string     $reasoningContent = '',
        ?Usage     $usage = null,
        array      $responseData = [],
        bool       $isPartial = false,
        array      $partialResponses = []
    ) {
        $this->content = $content;
        $this->finishReason = $finishReason;
        $this->toolCalls = $toolCalls ?? new ToolCalls();
        $this->reasoningContent = $reasoningContent;
        $this->responseData = $responseData;
        $this->usage = $usage ?? new Usage();
        $this->isPartial = $isPartial;
        $this->partialResponses = $partialResponses;
    }

    // STATIC ////////////////////////////////////////////////

    /**
     * Create an InferenceResponse from an array of PartialInferenceResponses.
     *
     * @param PartialInferenceResponse[] $partialResponses
     * @return InferenceResponse
     */
    public static function fromPartialResponses(array $partialResponses = []) : self {
        $response = new self(isPartial: true);
        foreach ($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            $response->applyPartialResponse($partialResponse);
        }
        $response->toolCalls = ToolCalls::fromArray(self::makeTools($partialResponses));
        return $response;
    }

    // PUBLIC ////////////////////////////////////////////////

    /**
     * Checks if the response has a processed / transformed value.
     *
     * @param mixed $value
     * @return InferenceResponse
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

    public function value() : mixed {
        return $this->value;
    }

    public function hasContent() : bool {
        return $this->content !== '';
    }

    public function content() : string {
        return $this->content;
    }

    public function withContent(string $content) : self {
        $this->content = $content;
        return $this;
    }

    public function withReasoningContent(string $reasoningContent) : self {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }

    public function reasoningContent() : string {
        return $this->reasoningContent;
    }

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

    public function finishReason() : InferenceFinishReason {
        return InferenceFinishReason::fromText($this->finishReason);
    }

    public function hasFinishReason() : bool {
        return $this->finishReason !== '';
    }

    public function responseData() : array {
        return $this->responseData;
    }

    public function isPartial() : bool {
        return $this->isPartial;
    }

    public function partialResponses() : array {
        return $this->partialResponses;
    }

    public function lastPartialResponse() : ?PartialInferenceResponse {
        return end($this->partialResponses) ?: null;
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
            toolCalls: $this->toolCalls->clone(),
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
     * @param PartialInferenceResponse $partialResponse
     */
    private function applyPartialResponse(PartialInferenceResponse $partialResponse) : void {
        $this->content .= $partialResponse->contentDelta ?? '';
        $this->reasoningContent .= $partialResponse->reasoningContentDelta ?? '';
        $this->usage()->accumulate($partialResponse->usage);
        if (!empty($partialResponse->responseData)) {
            $this->responseData[] = $partialResponse->responseData;
        }
        if (!empty($partialResponse->finishReason)) {
            $this->finishReason = $partialResponse->finishReason;
            $this->isPartial = false;
            // once we have a finish reason, we are no longer partial
            // yet - it does not mean streaming is finished
            // we can still receive extra chunks - e.g. with usage data
        }
    }

    /**
     * Make a list of tool calls from the partial responses.
     *
     * @param PartialInferenceResponse[] $partialResponses
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
