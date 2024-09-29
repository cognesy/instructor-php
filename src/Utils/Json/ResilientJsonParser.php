<?php

namespace Cognesy\Instructor\Utils\Json;

class ResilientJsonParser
{
    private string $input;
    private int $position = 0;
    private int $length;
    private bool $inCodeBlock = false;

    public function __construct(string $input) {
        $this->input = $input;
        $this->length = strlen($input);
    }

    // PUBLIC /////////////////////////////////////////////////////////////////

    public function parse(): mixed {
        $this->skipWhitespace();
        return $this->parseValue();
    }

    // INTERNAL ////////////////////////////////////////////////////////////////

    private function parseValue(): mixed {
        $char = $this->getCurrentChar();
        return match ($char) {
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

        while ($this->getCurrentChar() !== '}') {
            $key = $this->parseString();
            $this->skipWhitespace();
            $this->consume(':');
            $this->skipWhitespace();
            $value = $this->parseValue();
            $result[$key] = $value;

            $this->skipWhitespace();
            if ($this->getCurrentChar() === ',') {
                $this->consume(',');
                $this->skipWhitespace();
            }
        }

        $this->consume('}');
        return $result;
    }

    private function parseArray(): array {
        $result = [];
        $this->consume('[');
        $this->skipWhitespace();

        while ($this->getCurrentChar() !== ']') {
            $value = $this->parseValue();
            $result[] = $value;

            $this->skipWhitespace();
            if ($this->getCurrentChar() === ',') {
                $this->consume(',');
                $this->skipWhitespace();
            }
        }

        $this->consume(']');
        return $result;
    }

//    private function parseString(): string {
//        $result = '';
//        $this->consume('"');
//
//        while (true) {
//            $char = $this->getCurrentChar();
//            if ($char === '"' && $this->getPreviousChar() !== '\\') {
//                break;
//            }
//            if ($char === "\n" || $char === "\r") {
//                $result .= '\n';
//                $this->position++;
//            } elseif ($char === '\\') {
//                $result .= $char . $this->getNextChar();
//                $this->position += 2;
//            } else {
//                $result .= $char;
//                $this->position++;
//            }
//        }
//
//        $this->consume('"');
//        return $result;
//    }

    private function parseString(): string
    {
        $result = '';
        $this->consume('"');

        while (true) {
            $char = $this->getCurrentChar();
            if ($char === '`' && $this->getNextChar() === '`' && $this->getNextNextChar() === '`') {
                $this->inCodeBlock = !$this->inCodeBlock;
                $result .= '```';
                $this->position += 3;
                continue;
            }
            if ($char === '"' && $this->getPreviousChar() !== '\\' && !$this->inCodeBlock) {
                break;
            }
            if ($char === "\n" || $char === "\r") {
                $result .= '\n';
                $this->position++;
            } elseif ($char === '\\') {
                $result .= $char . $this->getNextChar();
                $this->position += 2;
            } else {
                $result .= $char;
                $this->position++;
            }
        }

        $this->consume('"');
        return $result;
    }

    private function parseNumber(): float|int {
        $start = $this->position;
        while (preg_match('/[\d.+-e]/i', $this->getCurrentChar())) {
            $this->position++;
        }
        $numberString = substr($this->input, $start, $this->position - $start);
        return is_numeric($numberString)
            ? $this->toNumber($numberString)
            : 0;
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

    private function skipWhitespace(): void {
        while ($this->position < $this->length && ctype_space($this->getCurrentChar())) {
            $this->position++;
        }
    }

    private function consume(string $expected): void {
        $length = strlen($expected);
        if (substr($this->input, $this->position, $length) !== $expected) {
            throw new \RuntimeException("Expected '$expected' at position {$this->position}");
        }
        $this->position += $length;
    }

    private function getCurrentChar(): string {
        return $this->position < $this->length ? $this->input[$this->position] : '';
    }

    private function getNextChar(): string {
        return $this->position + 1 < $this->length ? $this->input[$this->position + 1] : '';
    }

    private function getPreviousChar(): string {
        return $this->position > 0 ? $this->input[$this->position - 1] : '';
    }

    private function getNextNextChar(): string
    {
        return $this->position + 2 < $this->length ? $this->input[$this->position + 2] : '';
    }

    private function toNumber(float|int|string $numberString) : float|int {
        return strpos($numberString, '.') !== false
            ? (float) $numberString
            : (int) $numberString;
    }
}