<?php declare(strict_types=1);

namespace Cognesy\Experimental\Interpreter;

/**
 * InterpreterOutcome
 * - The result after running one computation
 * - Both the produced value AND the updated world
 */
final readonly class InterpreterState
{
    public function __construct(
        public mixed $value,
        public InterpreterContext $context,
        public bool $isError = false,
        public ?string $errorMessage = null,
    ) {}

    public static function initial(?InterpreterContext $context = null) : self {
        return new self(
            value: null,
            context: $context ?? InterpreterContext::initial(),
            errorMessage: null,
        );
    }

    public static function correct(
        mixed $value,
        InterpreterContext $context,
    ) : self {
        return new self(
            value: $value,
            context: $context,
            isError: false,
            errorMessage: null,
        );
    }

    public static function failed(
        InterpreterContext $context,
        ?string $errorMessage = null,
    ) : self {
        return new self(
            value: null,
            context: $context,
            isError: true,
            errorMessage: $errorMessage,
        );
    }

    public function withValue(mixed $value) : self {
        return $this->with(value: $value);
    }

    public function withContext(InterpreterContext $context) : self {
        return $this->with(context: $context);
    }

    public function withError(string $errorMessage) : self {
        return $this->with(
            isError: true,
            errorMessage: $errorMessage
        );
    }

    // INTERNAL //////////////////////////////////////////

    private function with(
        mixed $value = null,
        ?InterpreterContext $context = null,
        ?bool $isError = null,
        ?string $errorMessage = null,
    ) : self {
        return new self(
            value: $value ?? $this->value,
            context: $context ?? $this->context,
            isError: $isError ?? $this->isError,
            errorMessage: $errorMessage ?? $this->errorMessage,
        );
    }
}