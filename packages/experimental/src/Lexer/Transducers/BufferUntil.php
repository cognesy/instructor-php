<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Transducers;

use Closure;
use Cognesy\Experimental\Lexer\Data\CharToken;
use Cognesy\Experimental\Lexer\Data\Token;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Buffers characters until a predicate is met, then emits a token.
 * Useful for multi-character tokens like strings, numbers, identifiers.
 */
final readonly class BufferUntil implements Transducer
{
    /**
     * @param Closure(CharToken, CharToken[]): bool $predicate When to emit (receives current char and buffer)
     * @param Closure(CharToken[]): Token $tokenizer How to create token from buffer
     * @param bool $includeTerminator Whether to include the character that triggered emission in the token
     */
    public function __construct(
        private Closure $predicate,
        private Closure $tokenizer,
        private bool $includeTerminator = false,
    ) {}

    public function __invoke(Reducer $reducer): Reducer
    {
        return new class(
            $reducer,
            $this->predicate,
            $this->tokenizer,
            $this->includeTerminator,
        ) implements Reducer {
            /** @var CharToken[] */
            private array $buffer = [];

            public function __construct(
                private Reducer $inner,
                private Closure $predicate,
                private Closure $tokenizer,
                private bool $includeTerminator,
            ) {}

            public function init(): mixed
            {
                return $this->inner->init();
            }

            public function step(mixed $accumulator, mixed $reducible): mixed
            {
                assert($reducible instanceof CharToken);

                if (($this->predicate)($reducible, $this->buffer)) {
                    if ($this->includeTerminator) {
                        $this->buffer[] = $reducible;
                    }

                    if (!empty($this->buffer)) {
                        $token = ($this->tokenizer)($this->buffer);
                        $this->buffer = [];
                        $accumulator = $this->inner->step($accumulator, $token);
                    }

                    if (!$this->includeTerminator) {
                        // Start new buffer with terminator
                        $this->buffer[] = $reducible;
                    }

                    return $accumulator;
                }

                $this->buffer[] = $reducible;
                return $accumulator;
            }

            public function complete(mixed $accumulator): mixed
            {
                // Flush remaining buffer
                if (!empty($this->buffer)) {
                    $token = ($this->tokenizer)($this->buffer);
                    $accumulator = $this->inner->step($accumulator, $token);
                }
                return $this->inner->complete($accumulator);
            }
        };
    }
}
