<?php declare(strict_types=1);

namespace Cognesy\Experimental\Lexer\Lexers;

use Cognesy\Experimental\Lexer\Data\CharToken;
use Cognesy\Experimental\Lexer\Data\Token;
use Cognesy\Experimental\Lexer\Transducers\WithPosition;
use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;

/**
 * INI file lexer.
 *
 * Token types:
 * - SECTION: Section name (between [ and ])
 * - LBRACKET: [
 * - RBRACKET: ]
 * - KEY: Key name
 * - EQUALS: =
 * - VALUE: Value
 * - COMMENT: Comment line (starting with ; or #)
 * - NEWLINE: Line break
 * - WHITESPACE: Spaces/tabs
 */
final readonly class IniLexer implements Transducer
{
    public function __invoke(Reducer $reducer): Reducer
    {
        return new class($reducer) implements Reducer {
            private array $buffer = [];
            private ?string $currentContext = null;
            private bool $inComment = false;

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

                // Handle newline - ends comment
                if ($char === "\n") {
                    if ($this->inComment) {
                        $accumulator = $this->emitToken($accumulator, 'COMMENT');
                        $this->inComment = false;
                    } else {
                        $accumulator = $this->emitBuffered($accumulator);
                    }

                    $token = new Token(
                        type: 'NEWLINE',
                        value: $char,
                        position: $reducible->position,
                    );
                    $this->currentContext = null;
                    return $this->inner->step($accumulator, $token);
                }

                // Skip \r
                if ($char === "\r") {
                    return $accumulator;
                }

                // Everything after ; or # is a comment
                if ($this->inComment) {
                    $this->buffer[] = $reducible;
                    return $accumulator;
                }

                // Start of comment
                if (($char === ';' || $char === '#') && empty($this->buffer)) {
                    $this->inComment = true;
                    return $accumulator;
                }

                // Section markers
                if ($char === '[' && empty($this->buffer)) {
                    $accumulator = $this->emitBuffered($accumulator);
                    $this->currentContext = 'SECTION';
                    return $accumulator;
                }

                if ($char === ']' && $this->currentContext === 'SECTION') {
                    $accumulator = $this->emitToken($accumulator, 'SECTION');
                    $this->currentContext = null;
                    return $accumulator;
                }

                // Equals sign
                if ($char === '=' && $this->currentContext === null) {
                    $accumulator = $this->emitToken($accumulator, 'KEY');
                    $token = new Token(
                        type: 'EQUALS',
                        value: $char,
                        position: $reducible->position,
                    );
                    $accumulator = $this->inner->step($accumulator, $token);
                    $this->currentContext = 'VALUE';
                    return $accumulator;
                }

                // Whitespace
                if (ctype_space($char) && empty($this->buffer)) {
                    // Skip leading whitespace
                    return $accumulator;
                }

                if (ctype_space($char) && $this->currentContext !== 'VALUE') {
                    // Whitespace ends key/section (but not value)
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

                $tokenType = match ($this->currentContext) {
                    'SECTION' => 'SECTION',
                    'VALUE' => 'VALUE',
                    default => 'KEY',
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
                $value = trim($value); // Trim whitespace from values

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
     * Create an INI lexer transformation.
     */
    public static function create(): array
    {
        return [
            new WithPosition(),
            new self(),
        ];
    }
}
