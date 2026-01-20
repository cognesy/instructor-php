<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction;

use Cognesy\Instructor\Extraction\Buffers\ExtractingBuffer;
use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Contracts\CanProvideContentBuffer;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Exceptions\ExtractionException;
use Cognesy\Instructor\Extraction\Extractors\BracketMatchingExtractor;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\MarkdownBlockExtractor;
use Cognesy\Instructor\Extraction\Extractors\PartialJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\ResilientJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\SmartBraceExtractor;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Service class for extracting structured content from LLM responses.
 *
 * Orchestrates extractors with first-success-wins behavior and optional events.
 */
class ResponseExtractor implements CanExtractResponse, CanProvideContentBuffer
{
    /** @var array<CanExtractResponse|class-string<CanExtractResponse>> */
    private array $extractors;

    /** @var array<CanExtractResponse|class-string<CanExtractResponse>>|null */
    private ?array $streamingExtractors;

    private ?EventDispatcherInterface $events;

    /**
     * @param array<CanExtractResponse|class-string<CanExtractResponse>>|null $extractors Custom extractors (default: standard chain)
     * @param array<CanExtractResponse|class-string<CanExtractResponse>>|null $streamingExtractors Streaming-specific extractors (null = use subset of $extractors)
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
     * @param CanExtractResponse|class-string<CanExtractResponse> ...$extractors
     */
    public static function withExtractors(CanExtractResponse|string ...$extractors): self
    {
        return new self($extractors);
    }

    /**
     * Get the default extraction chain in order.
     *
     * @return array<class-string<CanExtractResponse>>
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
     * @return array<class-string<CanExtractResponse>>
     */
    public static function defaultStreamingExtractors(): array
    {
        return [
            DirectJsonExtractor::class,
            ResilientJsonExtractor::class,
            PartialJsonExtractor::class,
        ];
    }

    #[\Override]
    public function extract(ExtractionInput $input): array
    {
        if (trim($input->content) === '') {
            throw new ExtractionException('Empty response content');
        }

        $buffer = ExtractingBuffer::empty(
            mode: $input->mode,
            extractors: $this->resolveExtractors(),
            response: $input->response,
            events: $this->events,
        )->assemble($input->content);

        $parsed = $buffer->parsed();
        if ($parsed !== null) {
            return $parsed;
        }

        $summary = $this->formatErrors($buffer->errors());
        $message = 'No structured content found in response';
        if ($summary !== '') {
            $message = "No structured content found in response. Tried: {$summary}";
        }

        throw new ExtractionException($message);
    }

    #[\Override]
    public function makeContentBuffer(OutputMode $mode): CanBufferContent
    {
        return ExtractingBuffer::empty(
            mode: $mode,
            extractors: $this->resolveStreamingExtractors(),
            response: null,
            events: null,
        );
    }

    /**
     * Get extractors currently configured for this service.
     *
     * @return array<CanExtractResponse|class-string<CanExtractResponse>>
     */
    public function extractors(): array
    {
        return $this->extractors;
    }

    #[\Override]
    public function name(): string
    {
        return 'response_extractor';
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    /**
     * Resolve extractor from class string if needed.
     *
     * @param CanExtractResponse|class-string<CanExtractResponse> $extractor
     */
    private function resolveExtractor(CanExtractResponse|string $extractor): CanExtractResponse
    {
        if (is_string($extractor)) {
            return new $extractor();
        }

        return $extractor;
    }

    /**
     * @return CanExtractResponse[]
     */
    private function resolveExtractors(): array
    {
        return array_map(
            fn($extractor) => $this->resolveExtractor($extractor),
            $this->extractors,
        );
    }

    /**
     * Get streaming extractors, resolved to instances.
     *
     * @return CanExtractResponse[]
     */
    private function resolveStreamingExtractors(): array
    {
        $extractors = $this->streamingExtractors ?? self::defaultStreamingExtractors();
        return array_map(
            fn($extractor) => $this->resolveExtractor($extractor),
            $extractors,
        );
    }

    /**
     * @param array<string, string> $errors
     */
    private function formatErrors(array $errors): string
    {
        if ($errors === []) {
            return '';
        }

        $messages = [];
        foreach ($errors as $name => $error) {
            $messages[] = "[{$name}] {$error}";
        }

        return implode('; ', $messages);
    }
}
