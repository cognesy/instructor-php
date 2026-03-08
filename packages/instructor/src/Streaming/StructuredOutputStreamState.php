<?php declare(strict_types=1);

namespace Cognesy\Instructor\Streaming;

use Cognesy\Instructor\Data\StructuredOutputResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\PartialInferenceDelta;
use Cognesy\Polyglot\Inference\Data\Usage;
use Cognesy\Polyglot\Inference\Streaming\StreamingUsageState;

final class StructuredOutputStreamState
{
    private string $content = '';
    private string $reasoningContent = '';
    private string $finishReason = '';
    private mixed $value = null;

    private string $contentDelta = '';
    private string $reasoningContentDelta = '';
    private string $toolId = '';
    private string $toolName = '';
    private string $toolArgs = '';

    /** @var array<string,array{id:string,name:string,args:string}> */
    private array $tools = [];
    private int $toolsCount = 0;
    private string $lastToolKey = '';

    private StreamingUsageState $usage;

    public function __construct()
    {
        $this->usage = new StreamingUsageState();
    }

    public static function empty(): self
    {
        return new self();
    }

    public function reset(): void
    {
        $this->content = '';
        $this->reasoningContent = '';
        $this->finishReason = '';
        $this->value = null;
        $this->contentDelta = '';
        $this->reasoningContentDelta = '';
        $this->toolId = '';
        $this->toolName = '';
        $this->toolArgs = '';
        $this->tools = [];
        $this->toolsCount = 0;
        $this->lastToolKey = '';
        $this->usage = new StreamingUsageState();
    }

    public function applyDelta(PartialInferenceDelta $delta): void
    {
        $this->contentDelta = $delta->contentDelta;
        $this->reasoningContentDelta = $delta->reasoningContentDelta;
        $this->toolId = match (true) {
            is_string($delta->toolId) => $delta->toolId,
            $delta->toolId !== null => $delta->toolId->toString(),
            default => '',
        };
        $this->toolName = $delta->toolName;
        $this->toolArgs = $delta->toolArgs;

        $this->content .= $this->contentDelta;
        $this->reasoningContent .= $this->reasoningContentDelta;
        $this->finishReason = match ($delta->finishReason) {
            '' => $this->finishReason,
            default => $delta->finishReason,
        };
        $this->usage->apply($delta->usage, $delta->usageIsCumulative);
        $this->accumulateToolDelta();
    }

    public function setValue(mixed $value): void
    {
        $this->value = $value;
    }

    public function clearValue(): void
    {
        $this->value = null;
    }

    public function value(): mixed
    {
        return $this->value;
    }

    public function hasValue(): bool
    {
        return $this->value !== null;
    }

    public function content(): string
    {
        return $this->content;
    }

    public function reasoningContent(): string
    {
        return $this->reasoningContent;
    }

    public function finishReason(): string
    {
        return $this->finishReason;
    }

    public function usage(): Usage
    {
        return $this->usage->toUsage();
    }

    public function toolArgsSnapshot(): string
    {
        if ($this->lastToolKey === '' || !isset($this->tools[$this->lastToolKey])) {
            return '';
        }

        return (string) ($this->tools[$this->lastToolKey]['args'] ?? '');
    }

    public function toolKey(): string
    {
        return $this->lastToolKey;
    }

    public function toolCalls(): ToolCalls
    {
        if ($this->tools === []) {
            return ToolCalls::empty();
        }

        $items = [];
        foreach ($this->tools as $entry) {
            $items[] = [
                'id' => $entry['id'] ?? '',
                'name' => $entry['name'] ?? '',
                'arguments' => $entry['args'] ?? '',
            ];
        }

        return ToolCalls::fromArray($items);
    }

    public function partialRawResponse(): InferenceResponse
    {
        return (new InferenceResponse(
            content: $this->content,
            finishReason: $this->finishReason,
            toolCalls: $this->toolCalls(),
            reasoningContent: $this->reasoningContent,
            usage: $this->usage(),
            isPartial: true,
        ))->withReasoningContentFallbackFromContent();
    }

    public function partialResponse(): StructuredOutputResponse
    {
        return StructuredOutputResponse::partial(
            value: $this->value,
            rawResponse: $this->partialRawResponse(),
            toolArgsSnapshot: $this->toolArgsSnapshot(),
        );
    }

    public function finalRawResponse(): InferenceResponse
    {
        return (new InferenceResponse(
            content: $this->content,
            finishReason: $this->finishReason,
            toolCalls: $this->toolCalls(),
            reasoningContent: $this->reasoningContent,
            usage: $this->usage(),
            isPartial: false,
        ))->withReasoningContentFallbackFromContent();
    }

    public function finalResponse(): StructuredOutputResponse
    {
        return StructuredOutputResponse::final(
            value: $this->value,
            rawResponse: $this->finalRawResponse(),
            toolArgsSnapshot: $this->toolArgsSnapshot(),
        );
    }

    private function accumulateToolDelta(): void
    {
        if ($this->toolId === '' && $this->toolName === '' && $this->toolArgs === '') {
            return;
        }

        $key = $this->resolveToolKey($this->toolId, $this->toolName);

        if ($this->toolId !== '' && ($key !== $this->lastToolKey || !isset($this->tools[$key]))) {
            $this->toolsCount += 1;
            $this->tools[$key] = ['id' => $this->toolId, 'name' => $this->toolName, 'args' => ''];
        }

        if ($this->toolId !== '' && $this->toolName !== '' && isset($this->tools[$key])) {
            $this->tools[$key]['name'] = $this->toolName;
        }

        if ($this->toolId === '' && $this->toolName !== '' && $key !== $this->lastToolKey) {
            $this->toolsCount += 1;
            $this->tools[$key] = ['id' => '', 'name' => $this->toolName, 'args' => ''];
        }

        $this->lastToolKey = $key;

        if ($this->toolArgs === '' || $key === '' || !isset($this->tools[$key])) {
            return;
        }

        $this->tools[$key]['args'] .= $this->toolArgs;
    }

    private function resolveToolKey(string $toolId, string $toolName): string
    {
        return match (true) {
            $toolId !== '' => 'id:' . $toolId,
            $toolName !== '' => 'name:' . $toolName . '#' . ($this->toolsCount + 1),
            $this->lastToolKey !== '' && isset($this->tools[$this->lastToolKey]) => $this->lastToolKey,
            default => '',
        };
    }

}
