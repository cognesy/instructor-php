<?php

declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Lexers;

use Cognesy\Experimental\Lexer\Data\CharToken;
use Cognesy\Experimental\Lexer\Data\Token;
use Cognesy\Experimental\Lexer\Transducers\WithPosition;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * YAML lexer (simplified - handles common cases).
 *
 * Token types:
 * - INDENT: Indentation (spaces at line start)
 * - DASH: - (list item marker)
 * - KEY: Key name
 * - COLON: :
 * - VALUE: Value
 * - COMMENT: Comment (# ...)
 * - NEWLINE: Line break
 * - STRING: Quoted string
 * - NUMBER: Number
 * - BOOLEAN: true/false
 * - NULL: null/~
 */
final readonly class YamlLexer implements Transducer
{
    public function __invoke(Reducer $reducer): Reducer
    {
        return new class($reducer) implements Reducer {
            private array $buffer = [];
            private bool $lineStart = true;
            private bool $inComment = false;
            private bool $inString = false;
            private ?string $stringDelimiter = null;
            private bool $afterColon = false;
            private int $indentCount = 0;

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
                    if ($this->inComment) {
                        $accumulator = $this->emitToken($accumulator, 'COMMENT');
                        $this->inComment = false;
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
                    $accumulator = $this->inner->step($accumulator, $token);

                    $this->lineStart = true;
                    $this->afterColon = false;
                    $this->indentCount = 0;

                    return $accumulator;
                }

                // Skip \r
                if ($char === "\r") {
                    return $accumulator;
                }

                // Inside string
                if ($this->inString) {
                    if ($char === $this->stringDelimiter) {
                        $accumulator = $this->emitToken($accumulator, 'STRING');
                        $this->inString = false;
                        $this->stringDelimiter = null;
                        return $accumulator;
                    }
                    $this->buffer[] = $reducible;
                    return $accumulator;
                }

                // Inside comment
                if ($this->inComment) {
                    $this->buffer[] = $reducible;
                    return $accumulator;
                }

                // Line start: count indentation
                if ($this->lineStart) {
                    if ($char === ' ') {
                        $this->indentCount++;
                        $this->buffer[] = $reducible;
                        return $accumulator;
                    }

                    // End of indentation
                    if ($this->indentCount > 0) {
                        $accumulator = $this->emitToken($accumulator, 'INDENT');
                    }
                    $this->lineStart = false;
                }

                // Start of comment
                if ($char === '#') {
                    $accumulator = $this->emitBuffered($accumulator);
                    $this->inComment = true;
                    return $accumulator;
                }

                // Start of quoted string
                if ($char === '"' || $char === "'") {
                    $accumulator = $this->emitBuffered($accumulator);
                    $this->inString = true;
                    $this->stringDelimiter = $char;
                    return $accumulator;
                }

                // List item marker
                if ($char === '-' && empty($this->buffer)) {
                    $token = new Token(
                        type: 'DASH',
                        value: $char,
                        position: $reducible->position,
                    );
                    return $this->inner->step($accumulator, $token);
                }

                // Colon (key-value separator)
                if ($char === ':') {
                    $accumulator = $this->emitToken($accumulator, 'KEY');
                    $token = new Token(
                        type: 'COLON',
                        value: $char,
                        position: $reducible->position,
                    );
                    $accumulator = $this->inner->step($accumulator, $token);
                    $this->afterColon = true;
                    return $accumulator;
                }

                // Whitespace (skip if not buffering)
                if ($char === ' ' && empty($this->buffer)) {
                    return $accumulator;
                }

                // Whitespace ends value/key
                if ($char === ' ' && !$this->afterColon) {
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
                $trimmedValue = trim($value);

                // Determine token type
                $tokenType = match (true) {
                    $trimmedValue === '' && str_starts_with($value, ' ') => 'INDENT',
                    in_array($trimmedValue, ['true', 'false', 'yes', 'no', 'on', 'off'], true) => 'BOOLEAN',
                    in_array($trimmedValue, ['null', '~'], true) => 'NULL',
                    is_numeric($trimmedValue) => 'NUMBER',
                    $this->afterColon => 'VALUE',
                    default => 'VALUE',
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

                // Don't trim indent tokens
                if ($type !== 'INDENT') {
                    $value = trim($value);
                }

                $token = new Token(
                    type: $type,
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
     * Create a YAML lexer transformation.
     */
    public static function create(): array
    {
        return [
            new WithPosition(),
            new self(),
        ];
    }
}
