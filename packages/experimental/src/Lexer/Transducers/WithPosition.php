<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Transducers;

use Cognesy\Experimental\Lexer\Data\CharToken;
use Cognesy\Experimental\Lexer\Data\Position;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * Transforms characters into CharTokens with position tracking.
 * Tracks line, column, and offset for each character.
 */
final readonly class WithPosition implements Transducer
{
    public function __construct(
        private Position $initialPosition = new Position(),
    ) {}

    public function __invoke(Reducer $reducer): Reducer
    {
        return new class($reducer, $this->initialPosition) implements Reducer {
            private Position $position;

            public function __construct(
                private Reducer $inner,
                Position $initialPosition,
            ) {
                $this->position = $initialPosition;
            }

            public function init(): mixed
            {
                return $this->inner->init();
            }

            public function step(mixed $accumulator, mixed $reducible): mixed
            {
                $char = is_string($reducible) ? $reducible : (string) $reducible;

                $charToken = new CharToken(
                    char: $char,
                    position: $this->position,
                );

                $this->position = $this->position->advance($char);

                return $this->inner->step($accumulator, $charToken);
            }

            public function complete(mixed $accumulator): mixed
            {
                return $this->inner->complete($accumulator);
            }
        };
    }
}
