<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

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
    public string $finishReason;

    public readonly string $contentDelta;
    public readonly string $reasoningContentDelta;
    public readonly string $toolId;
    public readonly string $toolName;
    public readonly string $toolArgs;

    public ?Usage $usage;
    public array $responseData;

    public function __construct(
        ?string $contentDelta = null,
        ?string $reasoningContentDelta = null,
        ?string $toolId = null,
        ?string $toolName = null,
        ?string $toolArgs = null,
        ?string $finishReason = null,
        ?Usage $usage = null,
        ?array $responseData = null,
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
        $this->responseData = $responseData ?? [];
        $this->content = '';
        $this->reasoningContent = '';
        //
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;
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

    public function toolName(): string {
        return $this->toolName ?? '';
    }

    public function toolArgs(): string {
        return $this->toolArgs ?? '';
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
        ?array $responseData = null,
    ): self {
        return new self(
            contentDelta: $contentDelta ?? $this->contentDelta,
            reasoningContentDelta: $reasoningContentDelta ?? $this->reasoningContentDelta,
            toolId: $toolId ?? $this->toolId,
            toolName: $toolName ?? $this->toolName,
            toolArgs: $toolArgs ?? $this->toolArgs,
            finishReason: $finishReason ?? $this->finishReason,
            usage: $usage ?? $this->usage,
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
            responseData: $data['response_data'] ?? [],
            id: $data['id'] ?? null,
            createdAt: isset($data['created_at']) ? new DateTimeImmutable($data['created_at']) : null,
            updatedAt: isset($data['updated_at']) ? new DateTimeImmutable($data['updated_at']) : null
        );
    }
}
