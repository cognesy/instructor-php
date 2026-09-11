<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Data;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;
use Cognesy\Messages\ToolCallId;

/** @implements IteratorAggregate<int, AssistantMessageChunk> */
final readonly class AssistantMessageChunks implements Countable, IteratorAggregate
{
    /** @var list<AssistantMessageChunk> */
    private array $chunks;

    public function __construct(AssistantMessageChunk ...$chunks)
    {
        $this->chunks = array_values($chunks);
    }

    public static function empty(): self
    {
        return new self();
    }

    public function add(AssistantMessageChunk $chunk): self
    {
        return new self(...[...$this->chunks, $chunk]);
    }

    public function withTextDelta(int|string $index, string $text): self
    {
        return match ($text) {
            '' => $this,
            default => $this->add(AssistantMessageChunk::textDelta($index, $text)),
        };
    }

    public function withReasoningDelta(int|string $index, string $text): self
    {
        return match ($text) {
            '' => $this,
            default => $this->add(AssistantMessageChunk::reasoningDelta($index, $text)),
        };
    }

    public function withToolCallDelta(
        int|string $index,
        ToolCallId|string|null $id = null,
        string $name = '',
        string $arguments = '',
    ): self {
        return $this->add(AssistantMessageChunk::toolCallDelta($index, $id, $name, $arguments));
    }

    /** @return list<AssistantMessageChunk> */
    public function all(): array
    {
        return $this->chunks;
    }

    public function isEmpty(): bool
    {
        return $this->chunks === [];
    }

    public function textDelta(): string
    {
        return $this->textFor(AssistantMessageChunkType::TextDelta);
    }

    public function reasoningDelta(): string
    {
        return $this->textFor(AssistantMessageChunkType::ReasoningDelta);
    }

    #[\Override]
    public function count(): int
    {
        return count($this->chunks);
    }

    #[\Override]
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->chunks);
    }

    private function textFor(AssistantMessageChunkType $type): string
    {
        $text = '';
        foreach ($this->chunks as $chunk) {
            if ($chunk->type === $type) {
                $text .= $chunk->text;
            }
        }
        return $text;
    }
}
