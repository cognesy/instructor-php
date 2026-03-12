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
use Cognesy\Instructor\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Service class for extracting structured content from LLM responses.
 *
 * Orchestrates extractors with first-success-wins behavior and optional events.
 */
class ResponseExtractor implements CanExtractResponse, CanProvideContentBuffer
{
    /** @var CanExtractResponse[] */
    private array $extractors;

    /** @var CanExtractResponse[]|null */
    private ?array $streamingExtractors;

    private ?EventDispatcherInterface $events;

    /**
     * @param CanExtractResponse[]|null $extractors Custom extractors (default: standard chain)
     * @param CanExtractResponse[]|null $streamingExtractors Streaming-specific extractors (null = use subset of $extractors)
     * @param EventDispatcherInterface|null $events Optional event dispatcher
     */
    public function __construct(
        ?array $extractors = null,
        ?array $streamingExtractors = null,
        ?EventDispatcherInterface $events = null,
    ) {
        $this->events = $events;
        $resolved = $extractors ?? self::defaultExtractors();
        $this->extractors = $events !== null ? self::attachEvents($resolved, $events) : $resolved;
        $resolvedStreaming = match (true) {
            $streamingExtractors !== null => $streamingExtractors,
            $extractors !== null => $extractors,
            default => null,
        };
        $this->streamingExtractors = ($resolvedStreaming !== null && $events !== null)
            ? self::attachEvents($resolvedStreaming, $events)
            : $resolvedStreaming;
    }

    /**
     * Set the event dispatcher for extraction events.
     */
    public function withEvents(EventDispatcherInterface $events): self
    {
        $clone = clone $this;
        $clone->events = $events;
        $clone->extractors = self::attachEvents($clone->extractors, $events);
        if ($clone->streamingExtractors !== null) {
            $clone->streamingExtractors = self::attachEvents($clone->streamingExtractors, $events);
        }
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
     */
    public static function fromExtractors(CanExtractResponse ...$extractors): self
    {
        return new self($extractors);
    }

    /**
     * Get the default extraction chain in order.
     *
     * @return CanExtractResponse[]
     */
    public static function defaultExtractors(): array
    {
        return [
            new DirectJsonExtractor(),
            new ResilientJsonExtractor(),
            new MarkdownBlockExtractor(),
            new BracketMatchingExtractor(),
            new SmartBraceExtractor(),
        ];
    }

    /**
     * Get the default streaming extractors (fast subset).
     *
     * @return CanExtractResponse[]
     */
    public static function defaultStreamingExtractors(): array
    {
        return [
            new DirectJsonExtractor(),
            new ResilientJsonExtractor(),
            new PartialJsonExtractor(),
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
            extractors: $this->extractors,
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
            extractors: $this->streamingExtractors ?? self::defaultStreamingExtractors(),
            response: null,
            events: null,
        );
    }

    /**
     * Get extractors currently configured for this service.
     *
     * @return CanExtractResponse[]
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
     * Attach events to extractors that support withEvents().
     *
     * @param CanExtractResponse[] $extractors
     * @return CanExtractResponse[]
     */
    private static function attachEvents(array $extractors, EventDispatcherInterface $events): array
    {
        return array_map(function (CanExtractResponse $extractor) use ($events) {
            if (!method_exists($extractor, 'withEvents')) {
                return $extractor;
            }
            $withEvents = $extractor->withEvents($events);
            return $withEvents instanceof CanExtractResponse ? $withEvents : $extractor;
        }, $extractors);
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
