<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Exceptions;

use Exception;
use Throwable;

/**
 * Base exception for all pipeline-related errors.
 */
class PipelineException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?Throwable $previous = null,
        private readonly mixed $context = null,
        private readonly string $operatorName = '',
    ) {
        parent::__construct($message, $code, $previous);
    }

    /**
     * Get additional context about the error.
     */
    public function getContext(): mixed {
        return $this->context;
    }

    /**
     * Get the name of the processor that caused the error.
     */
    public function getOperatorName(): string {
        return $this->operatorName;
    }

    /**
     * Create a new exception with processor context.
     */
    public static function fromProcessor(
        string $processorName,
        string $message,
        mixed $context = null,
        ?Throwable $previous = null,
    ): static {
        return new static(
            message: $message,
            previous: $previous,
            context: $context,
            operatorName: $processorName,
        );
    }

    /**
     * Create a new exception for invalid processor.
     */
    public static function invalidProcessor(string $reason, mixed $processor = null): static {
        return new static(
            message: "Invalid processor: {$reason}",
            context: $processor,
        );
    }

    /**
     * Create a new exception for empty pipeline.
     */
    public static function emptyPipeline(): static {
        return new static('Cannot process empty pipeline');
    }

    /**
     * Create a new exception for configuration errors.
     */
    public static function configurationError(string $message, mixed $config = null): static {
        return new static(
            message: "Pipeline configuration error: {$message}",
            context: $config,
        );
    }

    /**
     * Create a new exception for invalid handler.
     */
    public static function invalidHandler(string $reason, mixed $handler = null): static {
        return new static(
            message: "Invalid handler: {$reason}",
            context: $handler,
        );
    }
}