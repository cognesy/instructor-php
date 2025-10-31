<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Lexers;

use Cognesy\Experimental\Lexer\Data\CharToken;
use Cognesy\Experimental\Lexer\Data\Token;
use Cognesy\Experimental\Lexer\Transducers\WithPosition;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * HCL (HashiCorp Configuration Language) lexer.
 *
 * Token types:
 * - IDENTIFIER: Variable/block names
 * - STRING: Quoted string
 * - NUMBER: Number
 * - BOOLEAN: true/false
 * - LBRACE: {
 * - RBRACE: }
 * - LBRACKET: [
 * - RBRACKET: ]
 * - EQUALS: =
 * - COMMA: ,
 * - DOT: .
 * - COMMENT: # or //
 * - NEWLINE: Line break
 */
final readonly class HclLexer implements Transducer
{
    public function __invoke(Reducer $reducer): Reducer {
        return new HclLexerReducer($reducer);
    }

    /**
     * Create an HCL lexer transformation.
     */
    public static function create(): array {
        return [
            new WithPosition(),
            new self(),
        ];
    }
}

class HclLexerReducer implements Reducer {
    private array $buffer = [];
    private bool $inString = false;
    private bool $escapeNext = false;
    private bool $inComment = false;
    private bool $inLineComment = false;

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

        // Handle newline
        if ($char === "\n") {
            if ($this->inLineComment) {
                $accumulator = $this->emitToken($accumulator, 'COMMENT');
                $this->inLineComment = false;
            } elseif ($this->inString) {
                $this->buffer[] = $reducible;
                return $accumulator;
            } else {
                $accumulator = $this->emitBuffered($accumulator);
            }

            $token = new Token(
                type: 'NEWLINE',
                value: $char,
                position: $reducible->position,
            );
            return $this->inner->step($accumulator, $token);
        }

        // Skip \r
        if ($char === "\r") {
            return $accumulator;
        }

        // Inside line comment
        if ($this->inLineComment) {
            $this->buffer[] = $reducible;
            return $accumulator;
        }

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

        // Start of comment
        if ($char === '#') {
            $accumulator = $this->emitBuffered($accumulator);
            $this->inLineComment = true;
            return $accumulator;
        }

        // Check for // comment
        if ($char === '/' && !empty($this->buffer)) {
            $lastChar = end($this->buffer);
            if ($lastChar->char === '/') {
                // Remove the first / from buffer
                array_pop($this->buffer);
                $accumulator = $this->emitBuffered($accumulator);
                $this->inLineComment = true;
                return $accumulator;
            }
        }

        // Structural characters
        if (in_array($char, ['{', '}', '[', ']', '=', ',', '.'], strict: true)) {
            $accumulator = $this->emitBuffered($accumulator);

            $tokenType = match ($char) {
                '{' => 'LBRACE',
                '}' => 'RBRACE',
                '[' => 'LBRACKET',
                ']' => 'RBRACKET',
                '=' => 'EQUALS',
                ',' => 'COMMA',
                '.' => 'DOT',
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
            return $accumulator;
        }

        // Regular character
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

        // Determine token type
        $tokenType = match (true) {
            $value === 'true' || $value === 'false' => 'BOOLEAN',
            is_numeric($value) => 'NUMBER',
            default => 'IDENTIFIER',
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

    private function unescapeChar(CharToken $charToken): CharToken
    {
        $char = match ($charToken->char) {
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            '"' => '"',
            '\\' => '\\',
            default => $charToken->char,
        };

        return new CharToken($char, $charToken->position);
    }
}