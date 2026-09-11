<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Drivers\OpenResponses;

use Cognesy\Messages\ToolCallId;

/** Fully resolved identity and semantic projection of one OpenResponses stream event. */
final readonly class OpenResponsesStreamEvent
{
    /**
     * @param array<string,mixed> $wireData
     * @param null|array<string,mixed> $item
     * @param null|array<string,mixed> $response
     */
    public function __construct(
        private array $wireData,
        private string $type,
        private ?array $item,
        private ?array $response,
        private ?OpenResponseItemId $itemId,
        private ?ToolCallId $toolCallId,
        private string $toolName,
        private string $contentIndex,
        private string $textDelta,
        private string $reasoningDelta,
        private string $toolArgumentsDelta,
        private string $textBlockKey,
        private string $reasoningBlockKey,
        private string $toolBlockKey,
    ) {}

    /** @return array<string,mixed> */
    public function wireData(): array
    {
        return $this->wireData;
    }

    public function type(): string
    {
        return $this->type;
    }

    /** @return null|array<string,mixed> */
    public function item(): ?array
    {
        return $this->item;
    }

    /** @return null|array<string,mixed> */
    public function response(): ?array
    {
        return $this->response;
    }

    public function itemId(): ?OpenResponseItemId
    {
        return $this->itemId;
    }

    public function toolCallId(): ?ToolCallId
    {
        return $this->toolCallId;
    }

    public function toolName(): string
    {
        return $this->toolName;
    }

    public function contentIndex(): string
    {
        return $this->contentIndex;
    }

    public function textDelta(): string
    {
        return $this->textDelta;
    }

    public function reasoningDelta(): string
    {
        return $this->reasoningDelta;
    }

    public function toolArgumentsDelta(): string
    {
        return $this->toolArgumentsDelta;
    }

    public function textBlockKey(): string
    {
        return $this->textBlockKey;
    }

    public function reasoningBlockKey(): string
    {
        return $this->reasoningBlockKey;
    }

    public function toolBlockKey(): string
    {
        return $this->toolBlockKey;
    }

    public function isTextEvent(): bool
    {
        return str_starts_with($this->type, 'response.output_text')
            || str_starts_with($this->type, 'response.text');
    }

    public function isReasoningEvent(): bool
    {
        return str_starts_with($this->type, 'response.reasoning');
    }

    public function isToolEvent(): bool
    {
        if (str_starts_with($this->type, 'response.function_call_arguments')) {
            return true;
        }
        return ($this->item['type'] ?? '') === 'function_call';
    }

    public function hasToolData(): bool
    {
        return $this->toolCallId !== null
            || $this->toolName !== ''
            || $this->toolArgumentsDelta !== '';
    }
}
