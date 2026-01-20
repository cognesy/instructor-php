<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Buffers;

use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Utils\Json\Json;

/**
 * JSON content buffer for structured output mode.
 *
 * Assembles partial JSON chunks and normalizes them for deserialization.
 * Uses Json::fromPartial to handle incomplete JSON syntax.
 */
final readonly class JsonBuffer implements CanBufferContent
{
    private function __construct(
        private string $raw,
        private string $normalized,
    ) {}

    public static function empty(): self {
        return new self('', '');
    }

    #[\Override]
    public function assemble(string $delta): CanBufferContent {
        if (trim($delta) === '') {
            return $this;
        }

        $raw = $this->raw . $delta;
        // Avoid invoking partial JSON parsing on scalars (e.g., numeric-only deltas)
        // because the underlying PartialJsonParser expects object|array.
        // Normalize only when we have any structural JSON brace present.
        $hasBraces = str_contains($raw, '{') || str_contains($raw, '[');
        $normalized = match ($hasBraces) {
            true => Json::fromPartial($raw)->toString(),
            false => $this->normalized,
        };

        return new self($raw, $normalized);
    }

    #[\Override]
    public function raw(): string {
        return $this->raw;
    }

    #[\Override]
    public function normalized(): string {
        return $this->normalized;
    }

    #[\Override]
    public function parsed(): ?array {
        if ($this->normalized === '') {
            return null;
        }

        try {
            return Json::fromPartial($this->normalized())->toArray();
        } catch (\Throwable) {
            return null;
        }
    }

    #[\Override]
    public function isEmpty(): bool {
        return $this->normalized === '';
    }

    public function equals(JsonBuffer $other): bool {
        return $this->normalized === $other->normalized;
    }
}
