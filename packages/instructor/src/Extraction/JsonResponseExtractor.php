<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Extraction;

use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Contracts\ExtractionStrategy;
use Cognesy\Instructor\Extraction\Strategies\BracketMatchingStrategy;
use Cognesy\Instructor\Extraction\Strategies\DirectJsonStrategy;
use Cognesy\Instructor\Extraction\Strategies\MarkdownCodeBlockStrategy;
use Cognesy\Instructor\Extraction\Strategies\ResilientJsonStrategy;
use Cognesy\Instructor\Extraction\Strategies\SmartBraceMatchingStrategy;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Result\Result;

/**
 * Extracts JSON data from LLM responses into canonical array form.
 *
 * Handles various response formats using a pluggable strategy chain:
 * - DirectJsonStrategy: Clean JSON content
 * - ResilientJsonStrategy: Handles malformed JSON (trailing commas, etc.)
 * - MarkdownCodeBlockStrategy: JSON wrapped in ```json ... ```
 * - BracketMatchingStrategy: Simple first-{ to last-} matching
 * - SmartBraceMatchingStrategy: Handles escaped quotes in strings
 *
 * Tool calls (in Tools mode) are handled specially via InferenceResponse.
 *
 * This formalizes the extraction stage of the response pipeline,
 * producing a canonical array that can then be deserialized.
 */
class JsonResponseExtractor implements CanExtractResponse
{
    /** @var ExtractionStrategy[] */
    private array $strategies;

    /**
     * @param ExtractionStrategy[]|null $strategies Custom strategies (default: standard chain)
     */
    public function __construct(?array $strategies = null)
    {
        $this->strategies = $strategies ?? self::defaultStrategies();
    }

    /**
     * Create extractor with default strategy chain.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Create extractor with custom strategies.
     *
     * @param ExtractionStrategy ...$strategies
     */
    public static function withStrategies(ExtractionStrategy ...$strategies): self
    {
        return new self($strategies);
    }

    /**
     * Get the default extraction strategies in order.
     *
     * @return ExtractionStrategy[]
     */
    public static function defaultStrategies(): array
    {
        return [
            new DirectJsonStrategy(),
            new ResilientJsonStrategy(),
            new MarkdownCodeBlockStrategy(),
            new BracketMatchingStrategy(),
            new SmartBraceMatchingStrategy(),
        ];
    }

    #[\Override]
    public function extract(InferenceResponse $response, OutputMode $mode): Result
    {
        // Handle tool calls specially - they have structured arguments
        if (OutputMode::Tools->is($mode) && $response->hasToolCalls()) {
            return $this->extractFromToolCalls($response);
        }

        // For text content, use strategy chain
        $content = $response->content();

        if (empty($content)) {
            return Result::failure('Empty response content');
        }

        return $this->extractWithStrategies($content);
    }

    /**
     * Get strategies currently configured for this extractor.
     *
     * @return ExtractionStrategy[]
     */
    public function strategies(): array
    {
        return $this->strategies;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * Extract JSON from tool call arguments.
     *
     * @return Result<array<string, mixed>, string>
     */
    private function extractFromToolCalls(InferenceResponse $response): Result
    {
        $toolCalls = $response->toolCalls();

        if ($toolCalls->hasSingle()) {
            $args = $toolCalls->first()?->args() ?? [];
            return Result::success($args);
        }

        // Multiple tool calls - return array of all
        return Result::success($toolCalls->toArray());
    }

    /**
     * Try extraction strategies in order until one succeeds.
     *
     * @return Result<array<string, mixed>, string>
     */
    private function extractWithStrategies(string $content): Result
    {
        $errors = [];

        foreach ($this->strategies as $strategy) {
            $result = $strategy->extract($content);

            if ($result->isSuccess()) {
                $json = $result->unwrap();

                // Decode JSON string to array
                /** @var Result<array<string, mixed>, string> */
                return Result::try(fn() => json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR));
            }

            $errors[] = "[{$strategy->name()}] {$result->errorMessage()}";
        }

        $errorSummary = implode('; ', $errors);
        return Result::failure("No JSON found in response. Tried: {$errorSummary}");
    }
}
