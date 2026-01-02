<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Extraction;

use Cognesy\Instructor\Events\Extraction\ExtractionCompleted;
use Cognesy\Instructor\Events\Extraction\ExtractionFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStarted;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategyAttempted;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategyFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategySucceeded;
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
use Psr\EventDispatcher\EventDispatcherInterface;

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
    private ?EventDispatcherInterface $events;

    /**
     * @param ExtractionStrategy[]|null $strategies Custom strategies (default: standard chain)
     * @param EventDispatcherInterface|null $events Optional event dispatcher for extraction events
     */
    public function __construct(
        ?array $strategies = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->strategies = $strategies ?? self::defaultStrategies();
        $this->events = $events;
    }

    /**
     * Set the event dispatcher for extraction events.
     */
    public function withEvents(EventDispatcherInterface $events): self
    {
        $clone = clone $this;
        $clone->events = $events;
        return $clone;
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
        $this->dispatch(new ExtractionStarted([
            'content_length' => strlen($content),
            'strategies_count' => count($this->strategies),
        ]));

        $errors = [];
        $attemptIndex = 0;

        foreach ($this->strategies as $strategy) {
            $strategyName = $strategy->name();

            $this->dispatch(new ExtractionStrategyAttempted([
                'strategy' => $strategyName,
                'attempt_index' => $attemptIndex++,
            ]));

            $result = $strategy->extract($content);

            if ($result->isSuccess()) {
                $json = $result->unwrap();

                $this->dispatch(new ExtractionStrategySucceeded([
                    'strategy' => $strategyName,
                    'content_length' => strlen($json),
                ]));

                // Decode JSON string to array
                /** @var Result<array<string, mixed>, string> */
                $decoded = Result::try(fn() => json_decode($json, associative: true, flags: JSON_THROW_ON_ERROR));

                if ($decoded->isSuccess()) {
                    $this->dispatch(new ExtractionCompleted([
                        'strategy' => $strategyName,
                        'content_length' => strlen($json),
                    ]));
                }

                return $decoded;
            }

            $errorMessage = $result->errorMessage();
            $this->dispatch(new ExtractionStrategyFailed([
                'strategy' => $strategyName,
                'error' => $errorMessage,
            ]));

            $errors[$strategyName] = $errorMessage;
        }

        $strategyNames = array_keys($errors);
        $errorSummary = implode('; ', array_map(
            fn($name, $error) => "[{$name}] {$error}",
            $strategyNames,
            $errors,
        ));

        $this->dispatch(new ExtractionFailed([
            'strategies_tried' => $strategyNames,
            'errors' => $errors,
        ]));

        return Result::failure("No JSON found in response. Tried: {$errorSummary}");
    }

    /**
     * Dispatch an event if event dispatcher is available.
     */
    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
