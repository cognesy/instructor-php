<?php

namespace Cognesy\Instructor\Utils\Json;

namespace Cognesy\Instructor\Utils\Json;

class ResilientJsonParser
{
    private string $input;
    private int $position = 0;
    private int $length;

    public function __construct(string $input) {
        $this->input = trim($input);
        $this->length = strlen($input);
    }

    public function parse(): mixed {
        if (empty($this->input) || ($this->length === 0)) {
            return '';
        }
        $this->skipWhitespace();
        if ($this->position >= $this->length) {
            return '';
        }
        return $this->parseValue();
    }

    private function parseValue(): mixed {
        return match ($this->getCurrentChar()) {
            '{' => $this->parseObject(),
            '[' => $this->parseArray(),
            '"' => $this->parseString(),
            't' => $this->parseTrue(),
            'f' => $this->parseFalse(),
            'n' => $this->parseNull(),
            default => $this->parseNumber(),
        };
    }

    private function parseObject(): array {
        $result = [];
        $this->consume('{');
        $this->skipWhitespace();

        if ($this->getCurrentChar() === '}') {
            $this->consume('}');
            return $result;
        }

        do {
            $this->skipWhitespace();
            $key = $this->parseString();
            $this->skipWhitespace();
            $this->consume(':');
            $this->skipWhitespace();
            $value = $this->parseValue();
            $result[$key] = $value;
            $this->skipWhitespace();
        } while ($this->consumeIf(','));

        $this->consume('}');
        return $result;
    }

    private function parseArray(): array {
        $result = [];
        $this->consume('[');
        $this->skipWhitespace();

        if ($this->getCurrentChar() === ']') {
            $this->consume(']');
            return $result;
        }

        do {
            $this->skipWhitespace();
            $result[] = $this->parseValue();
            $this->skipWhitespace();
        } while ($this->consumeIf(','));

        $this->consume(']');
        return $result;
    }

    private function parseString(): string {
        $this->consume('"');
        $result = '';
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            if ($char === '"') {
                $this->position++;
                return $result;
            }
            if ($char === '\\') {
                $this->position++;
                if ($this->position >= $this->length) {
                    throw new \RuntimeException("Unterminated string escape at position {$this->position}");
                }
                $escapeChar = $this->input[$this->position];
                $result .= $this->parseEscapeChar($escapeChar);
            } else {
                $result .= $char;
            }
            $this->position++;
        }
        throw new \RuntimeException("Unterminated string at position {$this->position}");
    }

    private function parseEscapeChar(string $char): string {
        return match($char) {
            '"' => '"',
            '\\' => '\\',
            '/' => '/',
            'b' => "\b",
            'f' => "\f",
            'n' => "\n",
            'r' => "\r",
            't' => "\t",
            'u' => $this->parseUnicodeEscape(),
            default => throw new \RuntimeException("Invalid escape character '\\$char' at position {$this->position}")
        };
    }

    private function parseUnicodeEscape(): string {
        $hex = substr($this->input, $this->position + 1, 4);
        if (strlen($hex) !== 4 || !ctype_xdigit($hex)) {
            throw new \RuntimeException("Invalid Unicode escape sequence at position {$this->position}");
        }
        $this->position += 4;
        return html_entity_decode("&#x$hex;", ENT_QUOTES, 'UTF-8');
    }

    private function parseTrue(): bool {
        $this->consume('true');
        return true;
    }

    private function parseFalse(): bool {
        $this->consume('false');
        return false;
    }

    private function parseNull(): ?string {
        $this->consume('null');
        return null;
    }

    private function parseNumber(): float|int {
        $start = $this->position;
        $allowedChars = '0123456789.eE+-';
        $gotDecimalPoint = false;
        $gotExponent = false;

        while ($this->position < $this->length && strpos($allowedChars, $this->getCurrentChar()) !== false) {
            $char = $this->getCurrentChar();
            if ($char === '.') {
                if ($gotDecimalPoint) {
                    throw new \RuntimeException("Invalid number format: multiple decimal points at position {$this->position}");
                }
                $gotDecimalPoint = true;
            } elseif ($char === 'e' || $char === 'E') {
                if ($gotExponent) {
                    throw new \RuntimeException("Invalid number format: multiple exponents at position {$this->position}");
                }
                $gotExponent = true;
            }
            $this->position++;
        }

        $numberString = substr($this->input, $start, $this->position - $start);
        if (!is_numeric($numberString)) {
            throw new \RuntimeException("Invalid number format at position $start");
        }

        return $this->toNumber($numberString);
    }

    private function skipWhitespace(): void {
        while ($this->position < $this->length) {
            $char = $this->input[$this->position];
            if (ctype_space($char)) {
                $this->position++;
            } elseif ($char === '\\' && $this->position + 1 < $this->length) {
                $nextChar = $this->input[$this->position + 1];
                if (in_array($nextChar, ['n', 'r', 't', 'f', 'b'])) {
                    $this->position += 2;
                } else {
                    break;
                }
            } else {
                break;
            }
        }
    }

    private function consume(string $expected): void {
        $this->skipWhitespace();
        if (substr($this->input, $this->position, strlen($expected)) !== $expected) {
            throw new \RuntimeException("Expected '$expected' at position {$this->position}");
        }
        $this->position += strlen($expected);
    }

    private function consumeIf(string $expected): bool {
        $this->skipWhitespace();
        if (substr($this->input, $this->position, strlen($expected)) === $expected) {
            $this->position += strlen($expected);
            return true;
        }
        return false;
    }

    private function getCurrentChar(): string {
        return $this->position < $this->length ? $this->input[$this->position] : '';
    }

    private function toNumber(string $numberString): float|int {
        if (strpos($numberString, '.') !== false || stripos($numberString, 'e') !== false) {
            return (float) $numberString;
        }
        return (int) $numberString;
    }
}
