<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Transducers;

use Cognesy\Experimental\Lexer\Data\CharToken;
use Cognesy\Experimental\Lexer\Data\LexerRule;
use Cognesy\Experimental\Lexer\Data\Token;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Matches character sequences against rules and emits tokens.
 * Rules are tried in order, first match wins.
 */
final readonly class PatternMatcher implements Transducer
{
    /**
     * @param LexerRule[] $rules
     * @param string $defaultTokenType Token type for unmatched characters
     */
    public function __construct(
        private array $rules,
        private string $defaultTokenType = 'UNKNOWN',
    ) {}

    public function __invoke(Reducer $reducer): Reducer
    {
        return new class(
            $reducer,
            $this->rules,
            $this->defaultTokenType,
        ) implements Reducer {
            /** @var CharToken[] */
            private array $buffer = [];
            private ?string $currentTokenType = null;

            public function __construct(
                private Reducer $inner,
                private array $rules,
                private string $defaultTokenType,
            ) {}

            public function init(): mixed
            {
                return $this->inner->init();
            }

            public function step(mixed $accumulator, mixed $reducible): mixed
            {
                assert($reducible instanceof CharToken);

                // Try to match against rules
                $matchedRule = $this->findMatchingRule($reducible);

                if ($matchedRule === null) {
                    // No rule matched, use default
                    $tokenType = $this->defaultTokenType;
                } else {
                    $tokenType = $matchedRule->tokenType;
                }

                // If token type changed, emit buffered token
                if ($this->currentTokenType !== null && $this->currentTokenType !== $tokenType) {
                    $accumulator = $this->emitToken($accumulator);
                }

                // Update current type
                $this->currentTokenType = $tokenType;

                // Add to buffer
                $this->buffer[] = $reducible;

                return $accumulator;
            }

            public function complete(mixed $accumulator): mixed
            {
                // Flush remaining buffer
                if (!empty($this->buffer)) {
                    $accumulator = $this->emitToken($accumulator);
                }
                return $this->inner->complete($accumulator);
            }

            private function findMatchingRule(CharToken $char): ?LexerRule
            {
                foreach ($this->rules as $rule) {
                    if ($rule->matches($char, $this->buffer)) {
                        return $rule;
                    }
                }
                return null;
            }

            private function emitToken(mixed $accumulator): mixed
            {
                if (empty($this->buffer)) {
                    return $accumulator;
                }

                $firstChar = $this->buffer[0];
                $lastChar = end($this->buffer);

                $value = implode('', array_map(fn($ct) => $ct->char, $this->buffer));

                $token = new Token(
                    type: $this->currentTokenType,
                    value: $value,
                    position: $firstChar->position,
                    endPosition: $lastChar->position,
                );

                $this->buffer = [];
                $this->currentTokenType = null;

                return $this->inner->step($accumulator, $token);
            }
        };
    }
}
