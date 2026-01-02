<?php declare(strict_types=1);

namespace Cognesy\Instructor\ResponseIterators\ModularPipeline\ContentBuffer;

use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Instructor\Extraction\Strategies\DirectJsonStrategy;
use Cognesy\Instructor\Extraction\Strategies\ResilientJsonStrategy;
use Cognesy\Utils\Json\Json;

/**
 * JSON buffer that uses pluggable extraction strategies for normalization.
 *
 * This buffer allows the same extraction strategies used for final response
 * extraction to be applied during streaming. Each time a delta is assembled,
 * the buffer attempts to extract valid JSON using the strategy chain.
 *
 * Provides streaming-aware extraction with fallback to partial JSON parsing
 * when no strategy succeeds (for incomplete streaming chunks).
 */
final readonly class ExtractingJsonBuffer implements ContentBuffer
{
    /** @var ExtractionStrategy[] */
    private array $strategies;

    private function __construct(
        private string $raw,
        private string $normalized,
        array $strategies,
    ) {
        $this->strategies = $strategies;
    }

    /**
     * Create empty buffer with default strategies.
     *
     * @param ExtractionStrategy[]|null $strategies Custom strategies (null = defaults)
     */
    public static function empty(?array $strategies = null): self
    {
        return new self('', '', $strategies ?? self::defaultStrategies());
    }

    /**
     * Create buffer with custom strategies.
     *
     * @param ExtractionStrategy ...$strategies
     */
    public static function withStrategies(ExtractionStrategy ...$strategies): self
    {
        return new self('', '', $strategies);
    }

    /**
     * Default strategies optimized for streaming (fast strategies first).
     *
     * @return ExtractionStrategy[]
     */
    public static function defaultStrategies(): array
    {
        return [
            new DirectJsonStrategy(),
            new ResilientJsonStrategy(),
        ];
    }

    #[\Override]
    public function assemble(string $delta): self
    {
        if (trim($delta) === '') {
            return $this;
        }

        $raw = $this->raw . $delta;
        $normalized = $this->extractOrParse($raw);

        return new self($raw, $normalized, $this->strategies);
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
    public function isEmpty(): bool
    {
        return $this->normalized === '';
    }

    public function equals(ExtractingJsonBuffer $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    /**
     * Get the strategies currently in use.
     *
     * @return ExtractionStrategy[]
     */
    public function strategies(): array
    {
        return $this->strategies;
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
        foreach ($this->strategies as $strategy) {
            $result = $strategy->extract($raw);
            if ($result->isSuccess()) {
                return $result->unwrap();
            }
        }

        // Fallback: use partial JSON parsing for incomplete streaming chunks
        return Json::fromPartial($raw)->toString();
    }
}
