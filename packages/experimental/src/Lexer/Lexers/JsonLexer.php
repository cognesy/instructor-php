<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Lexers;

use Cognesy\Experimental\Lexer\Data\CharToken;
use Cognesy\Experimental\Lexer\Data\Token;
use Cognesy\Experimental\Lexer\Transducers\WithPosition;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * JSON lexer.
 *
 * Token types:
 * - LBRACE: {
 * - RBRACE: }
 * - LBRACKET: [
 * - RBRACKET: ]
 * - COLON: :
 * - COMMA: ,
 * - STRING: String value (without quotes)
 * - NUMBER: Number (integer or float)
 * - TRUE: true
 * - FALSE: false
 * - NULL: null
 * - WHITESPACE: Spaces/tabs/newlines (can be filtered)
 */
final readonly class JsonLexer implements Transducer
{
    public function __invoke(Reducer $reducer): Reducer
    {
        return new class($reducer) implements Reducer {
            private array $buffer = [];
            private bool $inString = false;
            private bool $escapeNext = false;

            public function __construct(
                private Reducer $inner,
            ) {}

            public function init(): mixed
            {
                return $this->inner->init();
            }

            public function step(mixed $accumulator, mixed $reducible): mixed
            {
                assert($reducible instanceof CharToken);
                $char = $reducible->char;

                // Handle escape sequences in strings
                if ($this->escapeNext) {
                    $this->buffer[] = $this->unescapeChar($reducible);
                    $this->escapeNext = false;
                    return $accumulator;
                }

                // Inside string
                if ($this->inString) {
                    if ($char === '\\') {
                        $this->escapeNext = true;
                        return $accumulator;
                    }

                    if ($char === '"') {
                        // End of string
                        $accumulator = $this->emitToken($accumulator, 'STRING');
                        $this->inString = false;
                        return $accumulator;
                    }

                    $this->buffer[] = $reducible;
                    return $accumulator;
                }

                // Start of string
                if ($char === '"') {
                    $accumulator = $this->emitBuffered($accumulator);
                    $this->inString = true;
                    return $accumulator;
                }

                // Structural characters
                if (in_array($char, ['{', '}', '[', ']', ':', ','], strict: true)) {
                    $accumulator = $this->emitBuffered($accumulator);

                    $tokenType = match ($char) {
                        '{' => 'LBRACE',
                        '}' => 'RBRACE',
                        '[' => 'LBRACKET',
                        ']' => 'RBRACKET',
                        ':' => 'COLON',
                        ',' => 'COMMA',
                    };

                    $token = new Token(
                        type: $tokenType,
                        value: $char,
                        position: $reducible->position,
                    );

                    return $this->inner->step($accumulator, $token);
                }

                // Whitespace
                if (ctype_space($char)) {
                    $accumulator = $this->emitBuffered($accumulator);
                    // Skip whitespace (or emit WHITESPACE token if needed)
                    return $accumulator;
                }

                // Buffer character (for numbers, true, false, null)
                $this->buffer[] = $reducible;
                return $accumulator;
            }

            public function complete(mixed $accumulator): mixed
            {
                if (!empty($this->buffer)) {
                    $accumulator = $this->emitBuffered($accumulator);
                }
                return $this->inner->complete($accumulator);
            }

            private function emitBuffered(mixed $accumulator): mixed
            {
                if (empty($this->buffer)) {
                    return $accumulator;
                }

                $value = implode('', array_map(fn($ct) => $ct->char, $this->buffer));

                // Determine token type based on value
                $tokenType = match (true) {
                    $value === 'true' => 'TRUE',
                    $value === 'false' => 'FALSE',
                    $value === 'null' => 'NULL',
                    is_numeric($value) || $this->isNumber($value) => 'NUMBER',
                    default => 'UNKNOWN',
                };

                return $this->emitToken($accumulator, $tokenType);
            }

            private function emitToken(mixed $accumulator, string $type): mixed
            {
                if (empty($this->buffer)) {
                    return $accumulator;
                }

                $firstChar = $this->buffer[0];
                $lastChar = end($this->buffer);

                $value = implode('', array_map(fn($ct) => $ct->char, $this->buffer));

                $token = new Token(
                    type: $type,
                    value: $value,
                    position: $firstChar->position,
                    endPosition: $lastChar->position,
                );

                $this->buffer = [];

                return $this->inner->step($accumulator, $token);
            }

            private function isNumber(string $value): bool
            {
                // Match JSON number format: -?[0-9]+(\.[0-9]+)?([eE][+-]?[0-9]+)?
                return (bool) preg_match('/^-?[0-9]+(\.[0-9]+)?([eE][+-]?[0-9]+)?$/', $value);
            }

            private function unescapeChar(CharToken $charToken): CharToken
            {
                $char = match ($charToken->char) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    'b' => "\b",
                    'f' => "\f",
                    '"' => '"',
                    '\\' => '\\',
                    '/' => '/',
                    default => $charToken->char,
                };

                return new CharToken($char, $charToken->position);
            }
        };
    }

    /**
     * Create a JSON lexer transformation.
     */
    public static function create(): array
    {
        return [
            new WithPosition(),
            new self(),
        ];
    }
}
