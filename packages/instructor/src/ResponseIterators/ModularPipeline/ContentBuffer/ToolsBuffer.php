<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer;

use Cognesy\Utils\Json\Json;

/**
 * Tools mode content buffer for tool arguments.
 *
 * Assembles partial tool argument chunks and normalizes them for deserialization.
 * Uses Json::fromPartial to handle incomplete JSON syntax from streaming tool calls.
 */
final readonly class ToolsBuffer implements ContentBuffer
{
    private function __construct(
        private string $raw,
        private string $normalized,
    ) {}

    public static function empty(): self {
        return new self('', '');
    }

    #[\Override]
    public function assemble(string $delta): self {
        if (trim($delta) === '') {
            return $this;
        }

        $raw = $this->raw . $delta;
        // Normalize using partial JSON parser to handle incomplete chunks
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
    public function isEmpty(): bool {
        return $this->normalized === '';
    }

    public function equals(ContentBuffer $other): bool {
        return $this->normalized === $other->normalized();
    }
}
