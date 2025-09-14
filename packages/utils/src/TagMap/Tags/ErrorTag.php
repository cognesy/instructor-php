<?php declare(strict_types=1);

namespace Cognesy\Utils\TagMap\Tags;

use Cognesy\Utils\Result\Result;
use Cognesy\Utils\TagMap\Contracts\TagInterface;
use Throwable;

/**
 * Tag for tracking error information alongside the main Result-based error handling.
 *
 * This tag allows custom error handling, logging, and tracing while the main
 * error flow continues to use Result objects for type-safe error propagation.
 *
 * Use cases:
 * - Error logging and metrics
 * - Distributed tracing of errors
 * - Custom error categorization
 * - Recovery strategy metadata
 */
readonly class ErrorTag implements TagInterface
{
    public mixed $error;
    public ?string $context;
    public ?string $category;
    public ?float $timestamp;
    public array $metadata;

    public function __construct(
        mixed $error,
        ?string $context = null,
        ?string $category = null,
        ?float $timestamp = null,
        array $metadata = [],
    ) {
        $this->error = $error;
        $this->context = $context;
        $this->category = $category ?? 'error';
        $this->timestamp = $timestamp ?? microtime(true);
        $this->metadata = $metadata;
    }

    /**
     * Create an ErrorTag from an exception.
     */
    public static function fromException(Throwable $exception, ?string $context = null): self {
        return new self(
            error: $exception,
            context: $context,
            category: $exception::class,
            metadata: [
                'message' => $exception->getMessage(),
                'file' => $exception->getFile(),
                'line' => $exception->getLine(),
                'trace' => $exception->getTraceAsString(),
            ],
        );
    }

    public static function fromResult(Result $result, ?string $context = null): self {
        return new self(
            error: $result->exception(),
            context: $context,
            category: 'result_error',
            metadata: [
                'message' => $result->exception()?->getMessage() ?? 'Unknown error',
                'file' => $result->exception()?->getFile(),
                'line' => $result->exception()?->getLine(),
                'trace' => $result->exception()?->getTraceAsString(),
            ],
        );
    }

    /**
     * Create an ErrorTag from a string message.
     */
    public static function fromMessage(string $message, ?string $context = null, ?string $category = null): self {
        return new self(
            error: $message,
            context: $context,
            category: $category ?? 'error',
        );
    }

    /**
     * Create a new ErrorTag with additional context.
     */
    public function withContext(string $context): self {
        return new self(
            error: $this->error,
            context: $context,
            category: $this->category,
            timestamp: $this->timestamp,
            metadata: $this->metadata,
        );
    }

    /**
     * Create a new ErrorTag with additional metadata.
     */
    public function withMetadata(array $metadata): self {
        return new self(
            error: $this->error,
            context: $this->context,
            category: $this->category,
            timestamp: $this->timestamp,
            metadata: array_merge($this->metadata, $metadata),
        );
    }

    /**
     * Get a human-readable error message.
     */
    public function getMessage(): string {
        return match (true) {
            $this->error instanceof Throwable => $this->error->getMessage(),
            is_string($this->error) => $this->error,
            default => 'Unknown error: ' . json_encode($this->error),
        };
    }

    /**
     * Check if this error matches a specific category.
     */
    public function isCategory(string $category): bool {
        return $this->category === $category;
    }
}