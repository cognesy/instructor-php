<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Inference\Collections\ToolCalls;
use Cognesy\Polyglot\Inference\Enums\InferenceFinishReason;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Json\Json;
use DateTimeImmutable;

/**
 * Represents a response from the LLM.
 */
final readonly class InferenceResponse
{
    private const THINK_START_TAG = '<think>';
    private const THINK_END_TAG = '</think>';

    public InferenceResponseId $id;
    public DateTimeImmutable $createdAt;
    public DateTimeImmutable $updatedAt;

    private mixed $value;

    private string $content;
    private string $reasoningContent;
    private string $finishReason;

    private ToolCalls $toolCalls;
    private Usage $usage;
    private HttpResponse $responseData;

    private bool $isPartial;

    public function __construct(
        string $content = '',
        string $finishReason = '',
        ?ToolCalls $toolCalls = null,
        string $reasoningContent = '',
        ?Usage $usage = null,
        ?HttpResponse $responseData = null,
        bool $isPartial = false,
        mixed $value = null, // processed / transformed value
        //
        ?InferenceResponseId $id = null, // for deserialization
        ?DateTimeImmutable $createdAt = null, // for deserialization
        ?DateTimeImmutable $updatedAt = null, // for deserialization
    ) {
        $this->id = $id ?? InferenceResponseId::generate();
        $this->createdAt = $createdAt ?? new DateTimeImmutable();
        $this->updatedAt = $updatedAt ?? $this->createdAt;
        $this->value = $value;

        $this->content = $content;
        $this->finishReason = $finishReason;
        $this->toolCalls = $toolCalls ?? new ToolCalls();
        $this->reasoningContent = $reasoningContent;
        $this->responseData = $responseData ?? HttpResponse::empty();
        $this->usage = $usage ?? new Usage();

        $this->isPartial = $isPartial;
    }

    public static function empty() : self {
        return new self();
    }

    public static function fromAccumulatedPartial(PartialInferenceResponse $partial): InferenceResponse {
        $response = new InferenceResponse(
            content: $partial->content(),
            finishReason: $partial->finishReason(),
            toolCalls: $partial->toolCalls(),
            reasoningContent: $partial->reasoningContent(),
            usage: $partial->usage(),
            responseData: $partial->responseData,
            isPartial: false,
        );
        return $response->withReasoningContentFallbackFromContent();
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
        return $this->usage;
    }

    public function toolCalls(): ToolCalls {
        return $this->toolCalls;
    }

    public function finishReason(): InferenceFinishReason {
        return InferenceFinishReason::fromText($this->finishReason);
    }

    public function isPartial(): bool {
        return $this->isPartial;
    }

    public function responseData(): HttpResponse {
        return $this->responseData;
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
                $this->toolCalls->hasSingle() => Json::fromArray($this->toolCalls->first()?->args() ?? []),
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
        ?HttpResponse $responseData = null,
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

    public function withPricing(Pricing $pricing): self {
        return $this->with(usage: $this->usage->withPricing($pricing));
    }

    public function withReasoningContentFallbackFromContent(): self {
        if ($this->reasoningContent !== '') {
            return $this;
        }
        $split = self::splitThinkTags($this->content);
        if ($split === null) {
            return $this;
        }
        return $this->with(
            content: $split->content,
            reasoningContent: $split->reasoningContent,
        );
    }

    // SERIALIZATION /////////////////////////////////////////

    public function toArray(): array {
        return [
            'content' => $this->content,
            'finishReason' => $this->finishReason,
            'toolCalls' => $this->toolCalls->toArray(),
            'reasoningContent' => $this->reasoningContent,
            'usage' => $this->usage->toArray(),
            'responseData' => $this->responseData->toArray(), // raw response data
            'isPartial' => $this->isPartial,
            //
            'id' => $this->id->toString(),
            'createdAt' => $this->createdAt->format(DATE_ATOM),
            'updatedAt' => $this->updatedAt->format(DATE_ATOM),
        ];
    }

    public static function fromArray(array $data): self {
        $responseData = $data['responseData'] ?? null;

        return new self(
            content: $data['content'] ?? '',
            finishReason: $data['finishReason'] ?? '',
            toolCalls: (isset($data['toolCalls']) && is_array($data['toolCalls']))
                ? ToolCalls::fromArray($data['toolCalls'])
                : null,
            reasoningContent: $data['reasoningContent'] ?? '',
            usage: (isset($data['usage']) && is_array($data['usage']))
                ? Usage::fromArray($data['usage'])
                : null,
            responseData: (is_array($responseData) && $responseData !== [])
                ? HttpResponse::fromArray($responseData)
                : null,
            isPartial: $data['isPartial'] ?? false,
            //
            id: isset($data['id']) ? new InferenceResponseId($data['id']) : null,
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

    private static function splitThinkTags(string $content): ?ReasoningContentSplit {
        $start = strpos($content, self::THINK_START_TAG);
        if ($start === false) {
            return null;
        }
        $end = strpos($content, self::THINK_END_TAG);
        if ($end === false) {
            return null;
        }
        if ($end <= $start) {
            return null;
        }

        $startPos = $start + strlen(self::THINK_START_TAG);
        $reasoning = substr($content, $startPos, $end - $startPos);
        if ($reasoning === '') {
            return null;
        }

        $before = substr($content, 0, $start);
        $after = substr($content, $end + strlen(self::THINK_END_TAG));
        $cleanContent = trim($before . $after);

        return new ReasoningContentSplit($cleanContent, $reasoning);
    }
}
