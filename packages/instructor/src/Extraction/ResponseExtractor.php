<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction;

use Cognesy\Instructor\Events\Extraction\ExtractionCompleted;
use Cognesy\Instructor\Events\Extraction\ExtractionFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStarted;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategyAttempted;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategyFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategySucceeded;
use Cognesy\Instructor\Extraction\Buffers\ExtractingJsonBuffer;
use Cognesy\Instructor\Extraction\Buffers\ToolsBuffer;
use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Contracts\CanParseContent;
use Cognesy\Instructor\Extraction\Contracts\CanProvideContentBuffer;
use Cognesy\Instructor\Extraction\Enums\DataFormat;
use Cognesy\Instructor\Extraction\Extractors\BracketMatchingExtractor;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\MarkdownBlockExtractor;
use Cognesy\Instructor\Extraction\Extractors\ResilientJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\SmartBraceExtractor;
use Cognesy\Instructor\Extraction\Parsers\JsonParser;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Cognesy\Utils\Result\Result;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Service class for extracting structured content from LLM responses.
 *
 * This is the main extraction service that orchestrates CanExtractContent extractors.
 * It follows the same service pattern as ResponseValidator and ResponseDeserializer:
 * - Orchestrates multiple extractors with first-success-wins behavior
 * - Supports lazy instantiation from class strings
 * - Emits events for debugging and monitoring
 *
 * The architecture uses two distinct layers:
 * 1. Data Access Layer: Gets content string from response based on mode
 * 2. Extraction Layer: Applies extractors to find structured content (format-agnostic)
 *
 * Tool calls are NOT treated specially - they're just a different data source.
 * The extraction logic is the same for all output modes.
 */
class ResponseExtractor implements CanExtractResponse, CanProvideContentBuffer
{
    /** @var array<CanExtractContent|class-string<CanExtractContent>> */
    private array $extractors;

    /** @var array<CanExtractContent|class-string<CanExtractContent>>|null */
    private ?array $streamingExtractors;

    private ?EventDispatcherInterface $events;

    /**
     * @param array<CanExtractContent|class-string<CanExtractContent>>|null $extractors Custom extractors (default: standard chain)
     * @param array<CanExtractContent|class-string<CanExtractContent>>|null $streamingExtractors Streaming-specific extractors (null = use subset of $extractors)
     * @param EventDispatcherInterface|null $events Optional event dispatcher
     */
    public function __construct(
        ?array $extractors = null,
        ?array $streamingExtractors = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->extractors = $extractors ?? self::defaultExtractors();
        $this->streamingExtractors = $streamingExtractors;
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
     * Create extractor with default configuration.
     */
    public static function default(): self
    {
        return new self();
    }

    /**
     * Create extractor with custom extractors.
     *
     * @param CanExtractContent|class-string<CanExtractContent> ...$extractors
     */
    public static function withExtractors(CanExtractContent|string ...$extractors): self
    {
        return new self($extractors);
    }

    /**
     * Get the default extraction chain in order.
     *
     * @return array<class-string<CanExtractContent>>
     */
    public static function defaultExtractors(): array
    {
        return [
            DirectJsonExtractor::class,
            ResilientJsonExtractor::class,
            MarkdownBlockExtractor::class,
            BracketMatchingExtractor::class,
            SmartBraceExtractor::class,
        ];
    }

    /**
     * Get the default streaming extractors (fast subset).
     *
     * @return array<class-string<CanExtractContent>>
     */
    public static function defaultStreamingExtractors(): array
    {
        return [
            DirectJsonExtractor::class,
            ResilientJsonExtractor::class,
        ];
    }

    #[\Override]
    public function extract(InferenceResponse $response, OutputMode $mode): Result
    {
        // 1. DATA ACCESS: Get content string based on mode (uniform for all modes)
        $content = $this->getContentString($response, $mode);

        if (empty($content)) {
            return Result::failure('Empty response content');
        }

        // 2. RESOLVE PARSER
        $format = $this->resolveFormat($mode);
        $parser = $this->resolveParser($format);

        // 3. EXTRACTION: Try extractors in order (format-agnostic)
        return $this->extractFromContent($content, $parser);
    }

    #[\Override]
    public function makeContentBuffer(OutputMode $mode): CanBufferContent
    {
        $extractors = $this->resolveStreamingExtractors();
        return match ($mode) {
            OutputMode::Tools => ToolsBuffer::empty(),
            default => ExtractingJsonBuffer::empty($extractors),
        };
    }

    /**
     * Get extractors currently configured for this service.
     *
     * @return array<CanExtractContent|class-string<CanExtractContent>>
     */
    public function extractors(): array
    {
        return $this->extractors;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * Uniform data access - no special cases, just different sources.
     */
    private function getContentString(InferenceResponse $response, OutputMode $mode): string
    {
        return match ($mode) {
            OutputMode::Tools => $this->getToolCallContent($response),
            default => $response->content(),
        };
    }

    /**
     * Get content from tool calls as JSON string.
     */
    private function getToolCallContent(InferenceResponse $response): string
    {
        $toolCalls = $response->toolCalls();

        if ($toolCalls->isEmpty()) {
            // Fallback for providers that return tool-call JSON in content.
            return $response->content();
        }

        if ($toolCalls->hasSingle()) {
            return json_encode($toolCalls->first()?->args() ?? [], JSON_THROW_ON_ERROR);
        }

        return json_encode($toolCalls->toArray(), JSON_THROW_ON_ERROR);
    }

    /**
     * Format-agnostic extraction - same logic for all content types.
     *
     * @return Result<array<string, mixed>, string>
     */
    private function extractFromContent(string $content, CanParseContent $parser): Result
    {
        $this->dispatch(new ExtractionStarted([
            'content_length' => strlen($content),
            'extractors_count' => count($this->extractors),
        ]));

        $errors = [];
        $attemptIndex = 0;

        foreach ($this->extractors as $extractor) {
            $extractor = $this->resolveExtractor($extractor);
            $extractorName = $extractor->name();

            $this->dispatch(new ExtractionStrategyAttempted([
                'strategy' => $extractorName,
                'attempt_index' => $attemptIndex++,
            ]));

            $result = $extractor->extract($content);

            if ($result->isSuccess()) {
                $rawContent = $result->unwrap();

                $this->dispatch(new ExtractionStrategySucceeded([
                    'strategy' => $extractorName,
                    'content_length' => strlen($rawContent),
                ]));

                // Parse content to array
                $decoded = $parser->parse($rawContent);

                if ($decoded->isSuccess()) {
                    $this->dispatch(new ExtractionCompleted([
                        'strategy' => $extractorName,
                        'content_length' => strlen($rawContent),
                    ]));
                }

                return $decoded;
            }

            $errorMessage = $result->errorMessage();
            $this->dispatch(new ExtractionStrategyFailed([
                'strategy' => $extractorName,
                'error' => $errorMessage,
            ]));

            $errors[$extractorName] = $errorMessage;
        }

        $extractorNames = array_keys($errors);
        $errorSummary = implode('; ', array_map(
            fn($name, $error) => "[{$name}] {$error}",
            $extractorNames,
            $errors,
        ));

        $this->dispatch(new ExtractionFailed([
            'strategies_tried' => $extractorNames,
            'errors' => $errors,
        ]));

        return Result::failure("No structured content found in response. Tried: {$errorSummary}");
    }

    /**
     * Resolve extractor from class string if needed.
     *
     * @param CanExtractContent|class-string<CanExtractContent> $extractor
     */
    private function resolveExtractor(CanExtractContent|string $extractor): CanExtractContent
    {
        if (is_string($extractor)) {
            return new $extractor();
        }
        return $extractor;
    }

    /**
     * Get streaming extractors, resolved to instances.
     *
     * @return CanExtractContent[]
     */
    private function resolveStreamingExtractors(): array
    {
        $extractors = $this->streamingExtractors ?? self::defaultStreamingExtractors();
        return array_map(
            fn($extractor) => $this->resolveExtractor($extractor),
            $extractors
        );
    }

    private function resolveFormat(OutputMode $mode): DataFormat
    {
        return match($mode) {
            OutputMode::Tools, OutputMode::Json, OutputMode::JsonSchema, OutputMode::MdJson => DataFormat::Json,
            // Future:
            // OutputMode::MdYaml, OutputMode::Yaml => DataFormat::Yaml,
            default => DataFormat::Json,
        };
    }

    private function resolveParser(DataFormat $format): CanParseContent
    {
        return match($format) {
            DataFormat::Json => new JsonParser(),
        };
    }

    /**
     * Dispatch an event if event dispatcher is available.
     */
    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
