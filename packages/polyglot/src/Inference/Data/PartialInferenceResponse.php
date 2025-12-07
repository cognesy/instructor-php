<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

class PartialInferenceResponse
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;
    public readonly DateTimeImmutable $updatedAt;

    private mixed $value = null; // data extracted from response or tool calls

    private string $content; // full content accumulated from deltas
    private string $reasoningContent; // full reasoning content accumulated from deltas
    private string $finishReason;

    public readonly string $contentDelta;
    public readonly string $reasoningContentDelta;
    public readonly string $toolId;
    public readonly string $toolName;
    public readonly string $toolArgs;

    public ?Usage $usage;
    private bool $usageIsCumulative;
    public ?HttpResponse $responseData;

    // INTERNAL STATE FOR ACCUMULATION ///////////////////////////////////
    // Accumulate tool calls across streaming deltas without materializing
    // thousands of PartialInferenceResponse objects. We store raw args
    // JSON strings and convert to ToolCalls lazily on access.
    // keys: either "id:<toolId>" or synthetic "name:<toolName>#<n>" when id is missing
    private array $tools = [];
    private int $toolsCount = 0;
    private string $lastToolKey = '';

    public function __construct(
        ?string $contentDelta = null,
        ?string $reasoningContentDelta = null,
        ?string $toolId = null,
        ?string $toolName = null,
        ?string $toolArgs = null,
        ?string $finishReason = null,
        ?Usage $usage = null,
        bool $usageIsCumulative = false,
        ?HttpResponse $responseData = null,
        //
        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->contentDelta = $contentDelta ?? '';
        $this->reasoningContentDelta = $reasoningContentDelta ?? '';
        $this->toolId = $toolId ?? '';
        $this->toolName = $toolName ?? '';
        $this->toolArgs = $toolArgs ?? '';
        $this->finishReason = $finishReason ?? '';
        $this->usage = $usage ?? new Usage();
        $this->usageIsCumulative = $usageIsCumulative;
        $this->responseData = $responseData ?? HttpResponse::empty();
        $this->content = '';
        $this->reasoningContent = '';
        //
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;
    }

    public static function empty() : self {
        return new self();
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
        return $this->usage ?? new Usage();
    }

    public function isUsageCumulative(): bool {
        return $this->usageIsCumulative;
    }

    public function toolName(): string {
        return $this->toolName ?? '';
    }

    public function toolArgs(): string {
        return $this->toolArgs ?? '';
    }

    public function finishReason(): string {
        return $this->finishReason ?? '';
    }

    // HAS/IS ///////////////////////////////////////////////////////

    public function hasToolArgs(): bool {
        // do not change to not empty, as it will return true for '0'
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

    public function with(
        ?string $contentDelta = null,
        ?string $reasoningContentDelta = null,
        ?string $toolId = null,
        ?string $toolName = null,
        ?string $toolArgs = null,
        ?string $finishReason = null,
        ?Usage $usage = null,
        ?bool $usageIsCumulative = null,
        ?HttpResponse $responseData = null,
    ): self {
        return new self(
            contentDelta: $contentDelta ?? $this->contentDelta,
            reasoningContentDelta: $reasoningContentDelta ?? $this->reasoningContentDelta,
            toolId: $toolId ?? $this->toolId,
            toolName: $toolName ?? $this->toolName,
            toolArgs: $toolArgs ?? $this->toolArgs,
            finishReason: $finishReason ?? $this->finishReason,
            usage: $usage ?? $this->usage,
            usageIsCumulative: $usageIsCumulative ?? $this->usageIsCumulative,
            responseData: $responseData ?? $this->responseData,
            //
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    public function withReasoningContent(string $reasoningContent): self {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }

    public function withContent(string $content): self {
        $this->content = $content;
        return $this;
    }

    public function withFinishReason(string $finishReason): self {
        $this->finishReason = $finishReason;
        return $this;
    }

    public function withValue(mixed $value): self {
        $this->value = $value;
        return $this;
    }

    public function withUsageCumulative(bool $isCumulative = true): self {
        $this->usageIsCumulative = $isCumulative;
        return $this;
    }

    public function withAccumulatedContent(PartialInferenceResponse $previous) : self {
        // Accumulate content using previous full content if available,
        // otherwise fall back to previous delta; then append current delta.
        $baseContent = $previous->content() !== ''
            ? $previous->content()
            : ($previous->contentDelta ?? '');
        $this->content = $baseContent . ($this->contentDelta ?? '');

        // Accumulate reasoning content similarly
        $baseReasoning = $previous->reasoningContent() !== ''
            ? $previous->reasoningContent()
            : ($previous->reasoningContentDelta ?? '');
        $this->reasoningContent = $baseReasoning . ($this->reasoningContentDelta ?? '');

        // Prefer current finishReason if provided, otherwise carry over previous
        $this->finishReason = $this->makeFinishReason($previous);

        // Accumulate usage counters
        $isCumulative = $this->usageIsCumulative || $previous->usageIsCumulative;
        $this->usageIsCumulative = $isCumulative;
        $this->usage = $this->accumulateUsage($previous->usage(), $this->usage(), $isCumulative);

        // Preserve first HttpResponse (buffered SSE stream reference)
        // Prefer non-default previous response when available.
        if ($previous->responseData instanceof HttpResponse) {
            // HttpResponse::empty() has statusCode 0; prefer any non-zero previous
            $prevStatus = $previous->responseData->statusCode();
            if ($prevStatus > 0) {
                $this->responseData = $previous->responseData;
            }
        }

        // Accumulate tool calls across deltas
        // Merge previous accumulated tools/state
        $this->tools = $previous->tools;
        $this->toolsCount = $previous->toolsCount;
        $this->lastToolKey = $previous->lastToolKey;

        // Determine if there is a tool delta in the current chunk
        $hasToolDelta = ($this->toolId !== '') || ($this->toolName !== '') || ($this->toolArgs !== '');
        if ($hasToolDelta) {
            // Determine the key to use for this tool delta
            $key = $this->resolveToolKey($this->toolId, $this->toolName);

            // Handle tool creation/updating based on the resolved key
            if ($this->toolId !== '' && ($key !== $this->lastToolKey || !isset($this->tools[$key]))) {
                // New tool call by id
                $this->toolsCount += 1;
                $this->tools[$key] = [
                    'id' => $this->toolId,
                    'name' => $this->toolName, // may be empty on first delta
                    'args' => '',
                ];
            } elseif ($this->toolId !== '' && $this->toolName !== '' && isset($this->tools[$key])) {
                // Update name if arrives later for existing tool
                $this->tools[$key]['name'] = $this->toolName;
            } elseif ($this->toolName !== '' && $key !== $this->lastToolKey) {
                // New tool by name
                $this->tools[$key] = [
                    'id' => '',
                    'name' => $this->toolName,
                    'args' => '',
                ];
            }

            // Update last key
            $this->lastToolKey = $key;

            // Append args delta if provided
            if ($this->toolArgs !== '' && $key !== '' && isset($this->tools[$key])) {
                $this->tools[$key]['args'] .= $this->toolArgs;
            }
        }
        return $this;
    }

    /**
     * Get accumulated ToolCalls converted from internal tool deltas.
     */
    public function toolCalls(): ToolCalls {
        if (empty($this->tools)) {
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

    // INTERNAL //////////////////////////////////////////////////////////

    /**
     * Resolve the key to use for tool tracking based on ID or name
     */
    private function resolveToolKey(string $toolId, string $toolName): string {
        if ($toolId !== '') {
            return 'id:' . $toolId;
        }
        // If tool name matches the last tool, reuse the same key to append args
        $name = $toolName !== '' ? $toolName : ($this->tools[$this->lastToolKey]['name'] ?? '');
        if ($this->lastToolKey !== '' && isset($this->tools[$this->lastToolKey])) {
            $lastToolName = $this->tools[$this->lastToolKey]['name'] ?? '';
            if ($lastToolName === $name) {
                return $this->lastToolKey;
            }
        }
        // New tool - generate new key with sequence
        $seq = $this->toolsCount + 1;
        return 'name:' . $name . '#' . $seq;
    }

    // TRANSFORMATIONS //////////////////////////////////////////////

    public function json(): string {
        if (!$this->hasContent()) {
            return '';
        }
        return Json::fromPartial($this->content)->toString();
    }

    // SERIALIZATION ////////////////////////////////////////////////

    public function toArray(): array {
        return [
            'content_delta' => $this->contentDelta,
            'reasoning_content_delta' => $this->reasoningContentDelta,
            'tool_id' => $this->toolId,
            'tool_name' => $this->toolName,
            'tool_args' => $this->toolArgs,
            'finish_reason' => $this->finishReason,
            'usage' => $this->usage?->toArray(),
            'usage_is_cumulative' => $this->usageIsCumulative,
            'response_data' => $this->responseData,
            'id' => $this->id,
            'created_at' => $this->createdAt->format(DATE_ATOM),
            'updated_at' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            contentDelta: $data['content_delta'] ?? '',
            reasoningContentDelta: $data['reasoning_content_delta'] ?? '',
            toolId: $data['tool_id'] ?? '',
            toolName: $data['tool_name'] ?? '',
            toolArgs: $data['tool_args'] ?? '',
            finishReason: $data['finish_reason'] ?? '',
            usage: isset($data['usage']) && is_array($data['usage']) ? Usage::fromArray($data['usage']) : null,
            usageIsCumulative: (bool) ($data['usage_is_cumulative'] ?? false),
            responseData: HttpResponse::fromArray($data['response_data'] ?? []),
            id: $data['id'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null
        );
    }

    // INTERNAL /////////////////////////////////////////////////////

    private function makeFinishReason(PartialInferenceResponse $previous) : string {
        if ($this->finishReason !== '') {
            return $this->finishReason;
        }
        return $previous->finishReason();
    }

    private function accumulateUsage(Usage $previous, Usage $current, bool $isCumulative): Usage {
        if ($current->total() === 0) {
            return Usage::copy($previous);
        }
        if ($previous->total() === 0) {
            return Usage::copy($current);
        }

        if ($isCumulative) {
            return new Usage(
                inputTokens: max($previous->inputTokens, $current->inputTokens),
                outputTokens: max($previous->outputTokens, $current->outputTokens),
                cacheWriteTokens: max($previous->cacheWriteTokens, $current->cacheWriteTokens),
                cacheReadTokens: max($previous->cacheReadTokens, $current->cacheReadTokens),
                reasoningTokens: max($previous->reasoningTokens, $current->reasoningTokens),
            );
        }

        return $current->withAccumulated($previous);
    }
}
