<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Buffers;

use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;

/**
 * Text content buffer for text-based modes.
 *
 * Simple string accumulation with trimming for normalization.
 */
final readonly class TextBuffer implements CanBufferContent
{
    private function __construct(
        private string $content,
    ) {}

    public static function empty(): self {
        return new self('');
    }

    #[\Override]
    public function assemble(string $delta): CanBufferContent {
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
    public function parsed(): ?array {
        return null;
    }

    #[\Override]
    public function isEmpty(): bool {
        return $this->content === '';
    }
}
