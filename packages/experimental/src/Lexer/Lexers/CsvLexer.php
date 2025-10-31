<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Lexers;

use Cognesy\Experimental\Lexer\Data\CharToken;
use Cognesy\Experimental\Lexer\Data\Token;
use Cognesy\Experimental\Lexer\Transducers\WithPosition;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * CSV lexer that handles quoted fields, escapes, and header rows.
 *
 * Token types:
 * - FIELD: Field value
 * - COMMA: Field delimiter
 * - NEWLINE: Row delimiter
 * - HEADER_FIELD: Header field (first row only if hasHeader=true)
 */
final readonly class CsvLexer implements Transducer
{
    public function __construct(
        private string $delimiter = ',',
        private string $quote = '"',
        private bool $hasHeader = true,
    ) {}

    public function __invoke(Reducer $reducer): Reducer
    {
        return new class(
            $reducer,
            $this->delimiter,
            $this->quote,
            $this->hasHeader,
        ) implements Reducer {
            private array $buffer = [];
            private bool $inQuotes = false;
            private bool $isFirstRow = true;
            private bool $escapeNext = false;

            public function __construct(
                private Reducer $inner,
                private string $delimiter,
                private string $quote,
                private bool $hasHeader,
            ) {}

            public function init(): mixed
            {
                return $this->inner->init();
            }

            public function step(mixed $accumulator, mixed $reducible): mixed
            {
                assert($reducible instanceof CharToken);
                $char = $reducible->char;

                // Handle escape sequences
                if ($this->escapeNext) {
                    $this->buffer[] = $reducible;
                    $this->escapeNext = false;
                    return $accumulator;
                }

                // Toggle quote state
                if ($char === $this->quote) {
                    $this->inQuotes = !$this->inQuotes;
                    // Don't include quotes in the value
                    return $accumulator;
                }

                // Inside quotes, everything is literal except quote escapes
                if ($this->inQuotes) {
                    if ($char === '\\') {
                        $this->escapeNext = true;
                        return $accumulator;
                    }
                    $this->buffer[] = $reducible;
                    return $accumulator;
                }

                // Outside quotes: handle delimiters
                if ($char === $this->delimiter) {
                    // Emit field token
                    $accumulator = $this->emitField($accumulator);
                    // Emit delimiter
                    $delimiterToken = new Token(
                        type: 'COMMA',
                        value: $char,
                        position: $reducible->position,
                    );
                    return $this->inner->step($accumulator, $delimiterToken);
                }

                if ($char === "\n" || $char === "\r") {
                    // Emit field token
                    $accumulator = $this->emitField($accumulator);

                    // Skip \r in \r\n
                    if ($char === "\r") {
                        return $accumulator;
                    }

                    // Emit newline
                    $newlineToken = new Token(
                        type: 'NEWLINE',
                        value: $char,
                        position: $reducible->position,
                    );
                    $accumulator = $this->inner->step($accumulator, $newlineToken);

                    // Mark that we've finished first row
                    if ($this->isFirstRow) {
                        $this->isFirstRow = false;
                    }

                    return $accumulator;
                }

                // Regular character
                $this->buffer[] = $reducible;
                return $accumulator;
            }

            public function complete(mixed $accumulator): mixed
            {
                // Flush remaining buffer
                if (!empty($this->buffer)) {
                    $accumulator = $this->emitField($accumulator);
                }
                return $this->inner->complete($accumulator);
            }

            private function emitField(mixed $accumulator): mixed
            {
                if (empty($this->buffer)) {
                    return $accumulator;
                }

                $firstChar = $this->buffer[0];
                $lastChar = end($this->buffer);

                $value = implode('', array_map(fn($ct) => $ct->char, $this->buffer));

                $tokenType = ($this->hasHeader && $this->isFirstRow) ? 'HEADER_FIELD' : 'FIELD';

                $token = new Token(
                    type: $tokenType,
                    value: $value,
                    position: $firstChar->position,
                    endPosition: $lastChar->position,
                );

                $this->buffer = [];

                return $this->inner->step($accumulator, $token);
            }
        };
    }

    /**
     * Create a CSV lexer transformation.
     */
    public static function create(
        string $delimiter = ',',
        string $quote = '"',
        bool $hasHeader = true,
    ): array {
        return [
            new WithPosition(),
            new self($delimiter, $quote, $hasHeader),
        ];
    }
}
