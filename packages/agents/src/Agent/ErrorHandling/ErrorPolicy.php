<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\ErrorHandling;

final readonly class ErrorPolicy
{
    public function __construct(
        public ErrorHandlingDecision $onToolError,
        public ErrorHandlingDecision $onModelError,
        public ErrorHandlingDecision $onValidationError,
        public ErrorHandlingDecision $onRateLimitError,
        public ErrorHandlingDecision $onTimeoutError,
        public ErrorHandlingDecision $onUnknownError,
        public int $maxRetries,
    ) {}

    public static function stopOnAnyError(): self {
        return new self(
            onToolError: ErrorHandlingDecision::Stop,
            onModelError: ErrorHandlingDecision::Stop,
            onValidationError: ErrorHandlingDecision::Stop,
            onRateLimitError: ErrorHandlingDecision::Stop,
            onTimeoutError: ErrorHandlingDecision::Stop,
            onUnknownError: ErrorHandlingDecision::Stop,
            maxRetries: 0,
        );
    }

    public static function retryToolErrors(int $maxRetries = 3): self {
        return new self(
            onToolError: ErrorHandlingDecision::Retry,
            onModelError: ErrorHandlingDecision::Stop,
            onValidationError: ErrorHandlingDecision::Retry,
            onRateLimitError: ErrorHandlingDecision::Retry,
            onTimeoutError: ErrorHandlingDecision::Retry,
            onUnknownError: ErrorHandlingDecision::Stop,
            maxRetries: $maxRetries,
        );
    }

    public static function ignoreToolErrors(): self {
        return new self(
            onToolError: ErrorHandlingDecision::Ignore,
            onModelError: ErrorHandlingDecision::Stop,
            onValidationError: ErrorHandlingDecision::Ignore,
            onRateLimitError: ErrorHandlingDecision::Retry,
            onTimeoutError: ErrorHandlingDecision::Retry,
            onUnknownError: ErrorHandlingDecision::Ignore,
            maxRetries: 0,
        );
    }

    public static function retryAll(int $maxRetries = 5): self {
        return new self(
            onToolError: ErrorHandlingDecision::Retry,
            onModelError: ErrorHandlingDecision::Retry,
            onValidationError: ErrorHandlingDecision::Retry,
            onRateLimitError: ErrorHandlingDecision::Retry,
            onTimeoutError: ErrorHandlingDecision::Retry,
            onUnknownError: ErrorHandlingDecision::Retry,
            maxRetries: $maxRetries,
        );
    }

    public function withMaxRetries(int $maxRetries): self {
        return new self(
            onToolError: $this->onToolError,
            onModelError: $this->onModelError,
            onValidationError: $this->onValidationError,
            onRateLimitError: $this->onRateLimitError,
            onTimeoutError: $this->onTimeoutError,
            onUnknownError: $this->onUnknownError,
            maxRetries: $maxRetries,
        );
    }

    public function withToolErrorHandling(ErrorHandlingDecision $decision): self {
        return new self(
            onToolError: $decision,
            onModelError: $this->onModelError,
            onValidationError: $this->onValidationError,
            onRateLimitError: $this->onRateLimitError,
            onTimeoutError: $this->onTimeoutError,
            onUnknownError: $this->onUnknownError,
            maxRetries: $this->maxRetries,
        );
    }

    public function evaluate(ErrorContext $context): ErrorHandlingDecision {
        if ($context->consecutiveFailures === 0) {
            return ErrorHandlingDecision::Ignore;
        }

        $decision = match ($context->type) {
            ErrorType::Tool => $this->onToolError,
            ErrorType::Model => $this->onModelError,
            ErrorType::Validation => $this->onValidationError,
            ErrorType::RateLimit => $this->onRateLimitError,
            ErrorType::Timeout => $this->onTimeoutError,
            ErrorType::Unknown => $this->onUnknownError,
        };

        if ($decision === ErrorHandlingDecision::Retry) {
            if ($context->consecutiveFailures >= $this->maxRetries) {
                return ErrorHandlingDecision::Stop;
            }
        }

        return $decision;
    }
}
