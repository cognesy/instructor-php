<?php declare(strict_types=1);

namespace Cognesy\Instructor\Extraction\Buffers;

use Cognesy\Instructor\Events\Extraction\ExtractionCompleted;
use Cognesy\Instructor\Events\Extraction\ExtractionFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStarted;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategyAttempted;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategyFailed;
use Cognesy\Instructor\Events\Extraction\ExtractionStrategySucceeded;
use Cognesy\Instructor\Extraction\Contracts\CanBufferContent;
use Cognesy\Instructor\Extraction\Contracts\CanExtractResponse;
use Cognesy\Instructor\Extraction\Data\ExtractionInput;
use Cognesy\Instructor\Extraction\Extractors\DirectJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\PartialJsonExtractor;
use Cognesy\Instructor\Extraction\Extractors\ResilientJsonExtractor;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Enums\OutputMode;
use Psr\EventDispatcher\EventDispatcherInterface;
use Throwable;

/**
 * Extraction-aware buffer for streaming and sync pipelines.
 *
 * Accumulates raw content, attempts extraction on each update,
 * and keeps the last successful parsed payload for partial updates.
 */
final readonly class ExtractingBuffer implements CanBufferContent
{
    /** @var CanExtractResponse[] */
    private array $extractors;

    /** @var array<string, string> */
    private array $errors;

    private function __construct(
        private string $raw,
        private string $normalized,
        private ?array $parsed,
        array $extractors,
        private OutputMode $mode,
        private ?InferenceResponse $response,
        private ?EventDispatcherInterface $events,
        array $errors = [],
    ) {
        $this->extractors = $extractors;
        $this->errors = $errors;
    }

    /**
     * Create empty buffer with default extractors.
     *
     * @param CanExtractResponse[]|null $extractors Custom extractors (null = defaults)
     */
    public static function empty(
        OutputMode $mode,
        ?array $extractors = null,
        ?InferenceResponse $response = null,
        ?EventDispatcherInterface $events = null,
    ): self {
        return new self(
            raw: '',
            normalized: '',
            parsed: null,
            extractors: $extractors ?? self::defaultExtractors(),
            mode: $mode,
            response: $response,
            events: $events,
            errors: [],
        );
    }

    /**
     * Create buffer with custom extractors.
     */
    public static function withExtractors(
        OutputMode $mode,
        CanExtractResponse ...$extractors,
    ): self {
        return new self(
            raw: '',
            normalized: '',
            parsed: null,
            extractors: $extractors,
            mode: $mode,
            response: null,
            events: null,
            errors: [],
        );
    }

    /**
     * Default extractors optimized for streaming.
     *
     * @return CanExtractResponse[]
     */
    public static function defaultExtractors(): array
    {
        return [
            new DirectJsonExtractor(),
            new ResilientJsonExtractor(),
            new PartialJsonExtractor(),
        ];
    }

    #[\Override]
    public function assemble(string $delta): CanBufferContent
    {
        if (trim($delta) === '') {
            return $this;
        }

        $raw = $this->raw . $delta;
        return $this->updateFromRaw($raw);
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
    public function parsed(): ?array
    {
        return $this->parsed;
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return $this->raw === '';
    }

    public function equals(ExtractingBuffer $other): bool
    {
        return $this->normalized === $other->normalized;
    }

    /**
     * Get the extractors currently in use.
     *
     * @return CanExtractResponse[]
     */
    public function extractors(): array
    {
        return $this->extractors;
    }

    /**
     * Get errors from the last extraction attempt.
     *
     * @return array<string, string>
     */
    public function errors(): array
    {
        return $this->errors;
    }

    // INTERNAL //////////////////////////////////////////////////////////////

    private function updateFromRaw(string $raw): self
    {
        $result = $this->attemptExtraction($raw);
        $parsed = $result['parsed'];
        $normalized = $result['normalized'];
        $errors = $result['errors'];

        if ($parsed === null) {
            return new self(
                raw: $raw,
                normalized: $this->normalized,
                parsed: $this->parsed,
                extractors: $this->extractors,
                mode: $this->mode,
                response: $this->response,
                events: $this->events,
                errors: $errors,
            );
        }

        return new self(
            raw: $raw,
            normalized: $normalized,
            parsed: $parsed,
            extractors: $this->extractors,
            mode: $this->mode,
            response: $this->response,
            events: $this->events,
            errors: $errors,
        );
    }

    /**
     * @return array{parsed: ?array, normalized: string, errors: array<string, string>}
     */
    private function attemptExtraction(string $raw): array
    {
        if (trim($raw) === '') {
            return [
                'parsed' => null,
                'normalized' => $this->normalized,
                'errors' => ['content' => 'Empty content'],
            ];
        }

        $this->dispatch(new ExtractionStarted([
            'content_length' => strlen($raw),
            'extractors_count' => count($this->extractors),
        ]));

        $errors = [];
        $attemptIndex = 0;

        foreach ($this->extractors as $extractor) {
            $extractorName = $extractor->name();
            $this->dispatch(new ExtractionStrategyAttempted([
                'strategy' => $extractorName,
                'attempt_index' => $attemptIndex++,
            ]));

            try {
                $input = ExtractionInput::fromContent($raw, $this->mode, $this->response);
                $parsed = $extractor->extract($input);
            } catch (Throwable $error) {
                $errors[$extractorName] = $error->getMessage();
                $this->dispatch(new ExtractionStrategyFailed([
                    'strategy' => $extractorName,
                    'error' => $error->getMessage(),
                ]));
                continue;
            }

            $normalized = $this->normalize($parsed);

            $this->dispatch(new ExtractionStrategySucceeded([
                'strategy' => $extractorName,
                'content_length' => strlen($normalized),
            ]));
            $this->dispatch(new ExtractionCompleted([
                'strategy' => $extractorName,
                'content_length' => strlen($normalized),
            ]));

            return [
                'parsed' => $parsed,
                'normalized' => $normalized,
                'errors' => [],
            ];
        }

        $this->dispatch(new ExtractionFailed([
            'strategies_tried' => array_keys($errors),
            'errors' => $errors,
        ]));

        return [
            'parsed' => null,
            'normalized' => $this->normalized,
            'errors' => $errors,
        ];
    }

    /**
     * Normalize parsed content for downstream use.
     *
     * @param array<array-key, mixed> $parsed
     */
    private function normalize(array $parsed): string
    {
        try {
            return json_encode($parsed, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            return $this->normalized;
        }
    }

    /**
     * Dispatch an event if event dispatcher is available.
     */
    private function dispatch(object $event): void
    {
        $this->events?->dispatch($event);
    }
}
