<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

class PartialInferenceResponse
{
    public readonly string $id;
    public readonly DateTimeImmutable $createdAt;

    private mixed $value = null; // data extracted from response or tool calls
    private string $content = '';
    private string $reasoningContent = '';

    public function __construct(
        public string $contentDelta = '',
        public string $reasoningContentDelta = '',
        public string $toolId = '',
        public string $toolName = '',
        public string $toolArgs = '',
        public string $finishReason = '',
        public ?Usage $usage = null,
        public array $responseData = [],
        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
    }

    // PUBLIC ////////////////////////////////////////////////

    public function hasValue(): bool {
        return $this->value !== null;
    }

    public function withValue(mixed $value): self {
        $this->value = $value;
        return $this;
    }

    public function value(): mixed {
        return $this->value;
    }

    public function hasContent(): bool {
        return $this->content !== '';
    }

    public function withContent(string $content): self {
        $this->content = $content;
        return $this;
    }

    public function content(): string {
        return $this->content;
    }

    public function reasoningContent(): string {
        return $this->reasoningContent;
    }

    public function withReasoningContent(string $reasoningContent): self {
        $this->reasoningContent = $reasoningContent;
        return $this;
    }

    public function hasReasoningContent(): bool {
        return $this->reasoningContent !== '';
    }

    public function json(): string {
        if (!$this->hasContent()) {
            return '';
        }
        return Json::fromPartial($this->content)->toString();
    }

    public function withFinishReason(string $finishReason): self {
        $this->finishReason = $finishReason;
        return $this;
    }

    public function usage(): Usage {
        return $this->usage ?? new Usage();
    }

    public function hasToolArgs(): bool {
        // do not change to not empty, as it will return true for '0'
        return '' !== ($this->toolArgs ?? '');
    }

    public function hasToolName(): bool {
        return '' !== ($this->toolName ?? '');
    }

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
        );
    }

    public function toolName(): string {
        return $this->toolName ?? '';
    }

    public function toolArgs(): string {
        return $this->toolArgs ?? '';
    }
}
