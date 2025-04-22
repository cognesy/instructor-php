<?php

namespace Cognesy\Polyglot\LLM\Data;

use Cognesy\Polyglot\LLM\Enums\LLMFinishReason;
use Cognesy\Utils\Json\Json;

/**
 * Represents a response from the LLM.
 */
class LLMResponse
{
    private mixed $value = null;

    public function __construct(
        private string $content = '',
        private string $finishReason = '',
        private ?ToolCalls $toolCalls = null,
        private string $reasoningContent = '',
        private ?Usage $usage = null,
        private array  $responseData = [],
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
    public static function fromPartialResponses(array $partialResponses) : LLMResponse {
        return (new self)->makeFromPartialResponses($partialResponses);
    }

    // PUBLIC ////////////////////////////////////////////////

    /**
     * Create an LLMResponse from a value.
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
     * Get the JSON representation of the response.
     * @return Json
     */
    public function json(): Json {
        return match(true) {
            $this->hasToolCalls() => match($this->toolCalls->hasSingle()) {
                true => $this->toolCalls->first()?->json(),
                default => $this->toolCalls->json(),
            },
            $this->hasContent() => Json::fromString($this->content),
            default => Json::none(),
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

    public function responseData() : array {
        return $this->responseData;
    }

    public function toArray() : array {
        return [
            'content' => $this->content,
            'reasoningContent' => $this->reasoningContent,
            'responseData' => $this->responseData,
            'finishReason' => $this->finishReason,
            'toolCalls' => $this->toolCalls?->toArray() ?? [],
            'usage' => $this->usage->toArray(),
        ];
    }

    // INTERNAL //////////////////////////////////////////////

    /**
     * @param PartialLLMResponse[] $partialResponses
     * @return LLMResponse
     */
    private function makeFromPartialResponses(array $partialResponses = []) : self {
        if (empty($partialResponses)) {
            return $this;
        }

        $content = '';
        $reasoningContent = '';
        foreach($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            $content .= $partialResponse->contentDelta;
            $reasoningContent .= $partialResponse->reasoningContentDelta;
            $this->responseData[] = $partialResponse->responseData;
            $this->usage()->accumulate($partialResponse->usage);
            $this->finishReason = $partialResponse->finishReason;
        }
        $this->content = $content;
        $this->reasoningContent = $reasoningContent;

        $tools = $this->makeTools($partialResponses);
        if (!empty($tools)) {
            $this->toolCalls = ToolCalls::fromArray($tools);
        }
        return $this;
    }

    private function makeTools(array $partialResponses): array {
        $tools = [];
        $currentTool = '';
        foreach ($partialResponses as $partialResponse) {
            if ($partialResponse === null) {
                continue;
            }
            if (('' !== ($partialResponse->toolName ?? ''))
                && ($currentTool !== ($partialResponse->toolName ?? ''))) {
                $currentTool = $partialResponse->toolName ?? '';
                $tools[$currentTool] = '';
            }
            if ('' !== $currentTool) {
                if (('' !== ($partialResponse->toolArgs ?? ''))) {
                    $tools[$currentTool] .= $partialResponse->toolArgs ?? '';
                }
            }
        }
        return $tools;
    }
}
