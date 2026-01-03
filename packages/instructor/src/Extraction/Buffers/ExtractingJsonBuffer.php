<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Buffers;

use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\ResilientJsonExtractor;
use Cognesy\Utils\Json\Json;
use Cognesy\Utils\Result\Result;

/**
 * JSON buffer that uses pluggable extraction strategies for normalization.
 *
 * This buffer allows the same extraction strategies used for final response
 * extraction to be applied during streaming. Each time a delta is assembled,
 * the buffer attempts to extract valid JSON using the extractor chain.
 *
 * Provides streaming-aware extraction with fallback to partial JSON parsing
 * when no extractor succeeds (for incomplete streaming chunks).
 */
final readonly class ExtractingJsonBuffer implements CanBufferContent
{
    /** @var CanExtractContent[] */
    private array $extractors;

    private function __construct(
        private string $raw,
        private string $normalized,
        array $extractors,
    ) {
        $this->extractors = $extractors;
    }

    /**
     * Create empty buffer with default extractors.
     *
     * @param CanExtractContent[]|null $extractors Custom extractors (null = defaults)
     */
    public static function empty(?array $extractors = null): self
    {
        return new self('', '', $extractors ?? self::defaultExtractors());
    }

    /**
     * Create buffer with custom extractors.
     *
     * @param CanExtractContent ...$extractors
     */
    public static function withExtractors(CanExtractContent ...$extractors): self
    {
        return new self('', '', $extractors);
    }

    /**
     * Default extractors optimized for streaming (fast extractors first).
     *
     * @return CanExtractContent[]
     */
    public static function defaultExtractors(): array
    {
        return [
            new DirectJsonExtractor(),
            new ResilientJsonExtractor(),
        ];
    }

    #[\Override]
    public function assemble(string $delta): CanBufferContent
    {
        if (trim($delta) === '') {
            return $this;
        }

        $raw = $this->raw . $delta;
        $normalized = $this->extractOrParse($raw);

        return new self($raw, $normalized, $this->extractors);
    }

    #[\Override]
    public function raw(): string
    {
        return $this->raw;
    }

    #[\Override]
    public function normalized(): string
    {
        return $this->normalized;
    }

    #[\Override]
    public function parsed(): Result
    {
        return Result::try(fn() => Json::fromPartial($this->normalized())->toArray());
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->normalized === '';
    }

    public function equals(ExtractingJsonBuffer $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    /**
     * Get the extractors currently in use.
     *
     * @return CanExtractContent[]
     */
    public function extractors(): array
    {
        return $this->extractors;
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    /**
     * Try extraction strategies first, fall back to partial JSON parsing.
     */
    private function extractOrParse(string $raw): string
    {
        // Skip extraction if no structural characters yet
        $hasBraces = str_contains($raw, '{') || str_contains($raw, '[');
        if (!$hasBraces) {
            return $this->normalized;
        }

        // Try extraction strategies
        foreach ($this->extractors as $extractor) {
            $result = $extractor->extract($raw);
            if ($result->isSuccess()) {
                return $result->unwrap();
            }
        }

        // Fallback: use partial JSON parsing for incomplete streaming chunks
        return Json::fromPartial($raw)->toString();
    }
}
