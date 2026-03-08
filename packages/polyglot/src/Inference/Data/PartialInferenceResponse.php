<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Polyglot\Inference\Collections\ToolCalls;

class PartialInferenceResponse
{
    private mixed $value = null;

    private string $content;
    private string $reasoningContent;
    private string $finishReason;

    public readonly string $contentDelta;
    public readonly string $reasoningContentDelta;
    public readonly string $toolId;
    private ?ToolCallId $toolCallId;
    public readonly string $toolName;
    public readonly string $toolArgs;

    public ?Usage $usage;
    private bool $usageIsCumulative;
    private int $usageInputTokens = 0;
    private int $usageOutputTokens = 0;
    private int $usageCacheWriteTokens = 0;
    private int $usageCacheReadTokens = 0;
    private int $usageReasoningTokens = 0;
    private ?Pricing $usagePricing = null;

    /** @var array<string,array{id:string,name:string,args:string}> */
    private array $tools = [];
    private int $toolsCount = 0;
    private string $lastToolKey = '';
    private ?ToolCalls $memoizedToolCalls = null;

    public function __construct(
        ?string $contentDelta = null,
        ?string $reasoningContentDelta = null,
        ToolCallId|string|null $toolId = null,
        ?string $toolName = null,
        ?string $toolArgs = null,
        ?string $finishReason = null,
        ?Usage $usage = null,
        bool $usageIsCumulative = false,
        ?int $usageInputTokens = null,
        ?int $usageOutputTokens = null,
        ?int $usageCacheWriteTokens = null,
        ?int $usageCacheReadTokens = null,
        ?int $usageReasoningTokens = null,
        ?Pricing $usagePricing = null,
    ) {
        $this->contentDelta = $contentDelta ?? '';
        $this->reasoningContentDelta = $reasoningContentDelta ?? '';
        $this->toolCallId = match (true) {
            $toolId instanceof ToolCallId => $toolId,
            is_string($toolId) && $toolId !== '' => ToolCallId::fromString($toolId),
            default => null,
        };
        $this->toolId = (string) ($this->toolCallId ?? '');
        $this->toolName = $toolName ?? '';
        $this->toolArgs = $toolArgs ?? '';
        $this->finishReason = $finishReason ?? '';
        $this->usage = $usage;
        $this->usageIsCumulative = $usageIsCumulative;
        $this->usageInputTokens = $usage?->inputTokens ?? ($usageInputTokens ?? 0);
        $this->usageOutputTokens = $usage?->outputTokens ?? ($usageOutputTokens ?? 0);
        $this->usageCacheWriteTokens = $usage?->cacheWriteTokens ?? ($usageCacheWriteTokens ?? 0);
        $this->usageCacheReadTokens = $usage?->cacheReadTokens ?? ($usageCacheReadTokens ?? 0);
        $this->usageReasoningTokens = $usage?->reasoningTokens ?? ($usageReasoningTokens ?? 0);
        $this->usagePricing = $usage?->pricing() ?? $usagePricing;
        $this->content = '';
        $this->reasoningContent = '';
    }

    public static function empty() : self {
        return new self();
    }

    /**
     * @param array<string,array{id:string,name:string,args:string}> $tools
     */
    public static function fromAccumulatedState(
        string $contentDelta = '',
        string $reasoningContentDelta = '',
        ToolCallId|string|null $toolId = null,
        string $toolName = '',
        string $toolArgs = '',
        string $finishReason = '',
        ?Usage $usage = null,
        bool $usageIsCumulative = false,
        ?int $usageInputTokens = null,
        ?int $usageOutputTokens = null,
        ?int $usageCacheWriteTokens = null,
        ?int $usageCacheReadTokens = null,
        ?int $usageReasoningTokens = null,
        ?Pricing $usagePricing = null,
        mixed $value = null,
        string $content = '',
        string $reasoningContent = '',
        array $tools = [],
        int $toolsCount = 0,
        string $lastToolKey = '',
    ) : self {
        $partial = new self(
            contentDelta: $contentDelta,
            reasoningContentDelta: $reasoningContentDelta,
            toolId: $toolId,
            toolName: $toolName,
            toolArgs: $toolArgs,
            finishReason: $finishReason,
            usage: $usage,
            usageIsCumulative: $usageIsCumulative,
            usageInputTokens: $usageInputTokens,
            usageOutputTokens: $usageOutputTokens,
            usageCacheWriteTokens: $usageCacheWriteTokens,
            usageCacheReadTokens: $usageCacheReadTokens,
            usageReasoningTokens: $usageReasoningTokens,
            usagePricing: $usagePricing,
        );

        $partial->value = $value;
        $partial->content = $content;
        $partial->reasoningContent = $reasoningContent;
        $partial->tools = $tools;
        $partial->toolsCount = $toolsCount;
        $partial->lastToolKey = $lastToolKey;
        return $partial;
    }

    // PUBLIC ////////////////////////////////////////////////

    public function value(): mixed {
        return $this->value;
    }

    public function content(): string {
        return $this->content;
    }

    public function reasoningContent(): string {
        return $this->reasoningContent;
    }

    public function usage(): Usage {
        return $this->usage ??= new Usage(
            inputTokens: $this->usageInputTokens,
            outputTokens: $this->usageOutputTokens,
            cacheWriteTokens: $this->usageCacheWriteTokens,
            cacheReadTokens: $this->usageCacheReadTokens,
            reasoningTokens: $this->usageReasoningTokens,
            pricing: $this->usagePricing,
        );
    }

    public function isUsageCumulative(): bool {
        return $this->usageIsCumulative;
    }

    public function toolName(): string {
        return $this->toolName ?? '';
    }

    public function toolId(): ?ToolCallId {
        return $this->toolCallId;
    }

    public function toolArgs(): string {
        return $this->toolArgs ?? '';
    }

    public function toolArgsSnapshot(): string {
        if (empty($this->tools)) {
            return '';
        }

        $lastKey = array_key_last($this->tools);
        if ($lastKey === null) {
            return '';
        }

        $lastTool = $this->tools[$lastKey] ?? null;
        if (!is_array($lastTool)) {
            return '';
        }

        return (string) ($lastTool['args'] ?? '');
    }

    public function finishReason(): string {
        return $this->finishReason ?? '';
    }

    // HAS/IS ///////////////////////////////////////////////////////

    public function hasToolArgs(): bool {
        return '' !== ($this->toolArgs ?? '');
    }

    public function hasToolName(): bool {
        return '' !== ($this->toolName ?? '');
    }

    public function hasReasoningContent(): bool {
        return $this->reasoningContent !== '';
    }

    public function hasContent(): bool {
        return $this->content !== '';
    }

    public function hasValue(): bool {
        return $this->value !== null;
    }

    // MUTATORS /////////////////////////////////////////////////////

    public function withValue(mixed $value): self {
        $copy = new self(
            contentDelta: $this->contentDelta,
            reasoningContentDelta: $this->reasoningContentDelta,
            toolId: $this->toolCallId,
            toolName: $this->toolName,
            toolArgs: $this->toolArgs,
            finishReason: $this->finishReason,
            usage: $this->usage,
            usageIsCumulative: $this->usageIsCumulative,
            usageInputTokens: $this->usageInputTokens,
            usageOutputTokens: $this->usageOutputTokens,
            usageCacheWriteTokens: $this->usageCacheWriteTokens,
            usageCacheReadTokens: $this->usageCacheReadTokens,
            usageReasoningTokens: $this->usageReasoningTokens,
            usagePricing: $this->usagePricing,
        );

        $copy->value = $value;
        $copy->content = $this->content;
        $copy->reasoningContent = $this->reasoningContent;
        $copy->tools = $this->tools;
        $copy->toolsCount = $this->toolsCount;
        $copy->lastToolKey = $this->lastToolKey;
        return $copy;
    }

    /**
     * Get accumulated ToolCalls converted from internal tool deltas.
     */
    public function toolCalls(): ToolCalls {
        if ($this->memoizedToolCalls !== null) {
            return $this->memoizedToolCalls;
        }
        if (empty($this->tools)) {
            return $this->memoizedToolCalls = ToolCalls::empty();
        }
        $items = [];
        foreach ($this->tools as $entry) {
            $items[] = [
                'id' => $entry['id'] ?? '',
                'name' => $entry['name'] ?? '',
                'arguments' => $entry['args'] ?? '',
            ];
        }
        return $this->memoizedToolCalls = ToolCalls::fromArray($items);
    }
}
