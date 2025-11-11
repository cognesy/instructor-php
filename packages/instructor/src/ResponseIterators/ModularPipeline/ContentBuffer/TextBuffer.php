<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer;

/**
 * Text content buffer for text-based modes.
 *
 * Simple string accumulation with trimming for normalization.
 */
final readonly class TextBuffer implements ContentBuffer
{
    private function __construct(
        private string $content,
    ) {}

    public static function empty(): self {
        return new self('');
    }

    #[\Override]
    public function assemble(string $delta): self {
        return new self($this->content . $delta);
    }

    #[\Override]
    public function raw(): string {
        return $this->content;
    }

    #[\Override]
    public function normalized(): string {
        return trim($this->content);
    }

    #[\Override]
    public function isEmpty(): bool {
        return $this->content === '';
    }
}
