<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Uuid;
use DateTimeImmutable;

/**
 * Represents a response from the LLM.
 */
final readonly class InferenceResponse
{
    public string $id;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;

    private mixed $value;

    private string $content;
    private string $reasoningContent;
    private string $finishReason;
    private ToolCalls $toolCalls;
    private Usage $usage;
    private array $responseData;
    private bool $isPartial;

    public function __construct(
        string $content = '',
        string $finishReason = '',
        ?ToolCalls $toolCalls = null,
        string $reasoningContent = '',
        ?Usage $usage = null,
        array $responseData = [],
        bool $isPartial = false,
        mixed $value = null, // processed / transformed value
        //
        ?string $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->id = $id ?? Uuid::uuid4();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;
        $this->value = $value;

        $this->content = $content;
        $this->finishReason = $finishReason;
        $this->toolCalls = $toolCalls ?? new ToolCalls();
        $this->reasoningContent = $reasoningContent;
        $this->responseData = $responseData;
        $this->usage = $usage ?? new Usage();
        $this->isPartial = $isPartial;
    }

    public static function empty() : self {
        return new self();
    }

    // ACCESSORS /////////////////////////////////////////////

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

    public function toolCalls(): ToolCalls {
        return $this->toolCalls ?? new ToolCalls();
    }

    public function finishReason(): InferenceFinishReason {
        return InferenceFinishReason::fromText($this->finishReason);
    }

    public function responseData(): array {
        return $this->responseData;
    }

    public function isPartial(): bool {
        return $this->isPartial;
    }

    // HAS/IS ////////////////////////////////////////////////

    public function hasValue(): bool {
        return $this->value !== null;
    }

    public function hasContent(): bool {
        return $this->content !== '';
    }

    public function hasReasoningContent(): bool {
        return $this->reasoningContent !== '';
    }

    public function hasToolCalls(): bool {
        return $this->toolCalls->hasAny();
    }

    public function hasFinishReason(): bool {
        return $this->finishReason !== '';
    }

    /**
     * Find the JSON data in the response - try checking for tool calls (if any are present)
     * or find and extract JSON from the returned content.
     *
     * @return Json
     */
    public function findJsonData(?OutputMode $mode = null): Json {
        return match (true) {
            is_null($mode) => Json::fromString($this->content),
            OutputMode::Tools->is($mode) && $this->hasToolCalls() => match (true) {
                $this->toolCalls->hasSingle() => Json::fromArray($this->toolCalls->first()->args()),
                default => Json::fromArray($this->toolCalls->toArray()),
            },
            //$this->hasContent() => Json::fromString($this->content),
            default => Json::fromString($this->content),
        };
    }

    // MUTATORS //////////////////////////////////////////////

    public function with(
        ?string $content = null,
        ?string $finishReason = null,
        ?ToolCalls $toolCalls = null,
        ?string $reasoningContent = null,
        ?Usage $usage = null,
        ?array $responseData = null,
        ?bool $isPartial = null,
        mixed $value = null,
    ): self {
        return new self(
            content: $content ?? $this->content,
            finishReason: $finishReason ?? $this->finishReason,
            toolCalls: $toolCalls ?? $this->toolCalls,
            reasoningContent: $reasoningContent ?? $this->reasoningContent,
            usage: $usage ?? $this->usage,
            responseData: $responseData ?? $this->responseData,
            isPartial: $isPartial ?? $this->isPartial,
            value: $value ?? $this->value,
            id: $this->id,
            createdAt: $this->createdAt,
            updatedAt: new DateTimeImmutable(),
        );
    }

    /**
     * Set the processed / transformed value of the response.
     *
     * @param mixed $value
     * @return static
     */
    public function withValue(mixed $value): static {
        return $this->with(value: $value);
    }

    public function withContent(string $content): self {
        return $this->with(content: $content);
    }

    // SERIALIZATION /////////////////////////////////////////

    public function toArray(): array {
        return [
            'content' => $this->content,
            'finishReason' => $this->finishReason,
            'toolCalls' => $this->toolCalls->toArray(),
            'reasoningContent' => $this->reasoningContent,
            'usage' => $this->usage->toArray(),
            'responseData' => $this->responseData, // raw response data
            'isPartial' => $this->isPartial,
            //
            'id' => $this->id,
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        return new self(
            content: $data['content'] ?? '',
            finishReason: $data['finishReason'] ?? '',
            toolCalls: isset($data['toolCalls']) ? ToolCalls::fromArray($data['toolCalls']) : null,
            reasoningContent: $data['reasoningContent'] ?? '',
            usage: isset($data['usage']) ? Usage::fromArray($data['usage']) : null,
            responseData: $data['responseData'] ?? [],
            isPartial: $data['isPartial'] ?? false,
            //
            id: $data['id'] ?? null,
            createdAt: isset($data['createdAt']) ? new DateTimeImmutable($data['createdAt']) : null,
            updatedAt: isset($data['updatedAt']) ? new DateTimeImmutable($data['updatedAt']) : null,
        );
    }

    public function hasFinishedWithFailure() : bool {
        return InferenceFinishReason::fromText($this->finishReason)->isOneOf(
            InferenceFinishReason::Error,
            InferenceFinishReason::ContentFilter,
            InferenceFinishReason::Length,
        );
    }
}
