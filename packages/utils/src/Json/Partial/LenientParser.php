<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

final class LenientParser
{
    /**
     * @return array|string|int|float|bool|null
     */
    public function parse(string $input): mixed {
        $tokenizer = new TolerantTokenizer($input);

        /** @var list<ParseFrame> $stack */
        $stack = [];
        $root = null;
        $lastType = null;

        while (($tok = $tokenizer->next()) !== null) {
            $tokType = $tok->type;
            switch ($tok->type) {
                case TokenType::LeftBrace:
                    $stack[] = new ObjectFrame();
                    break;

                case TokenType::LeftBracket:
                    $stack[] = new ArrayFrame();
                    break;

                case TokenType::RightBrace:
                case TokenType::RightBracket:
                    $completed = array_pop($stack);
                    if ($completed instanceof ObjectFrame) $completed->closeIfPending();
                    $this->attachToParentOrSetRoot($stack, $root, $completed?->getValue() ?? []);
                    break;

                case TokenType::String:
                case TokenType::StringPartial:
                case TokenType::Number:
                case TokenType::NumberPartial:
                case TokenType::True:
                case TokenType::False:
                case TokenType::Null:
                    $value = $this->valueFromToken($tok);

                    if ($stack === []) {
                        $root = $value;
                        break;
                    }

                    $top = $stack[array_key_last($stack)];

                    if (
                        $top instanceof ObjectFrame
                        && $lastType === TokenType::NumberPartial
                        && $tokType === TokenType::String
                        && !$top->hasPendingKey()
                    ) {
                        // Skip this stray bareword (e.g., "12abc" -> drop "abc")
                        $lastType = $tokType;
                        break;
                    }

                    if ($top instanceof ArrayFrame) {
                        $top->addValue($value);
                    } elseif ($top instanceof ObjectFrame) {
                        if (!$top->hasPendingKey()) {
                            $key = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                            $top->setPendingKey((string)$key);
                        } else {
                            $top->addValue($value);
                        }
                    }
                    break;

                case TokenType::Colon:
                case TokenType::Comma:
                    // no-op
                    break;
            }
            $lastType = $tokType;
        }

        // EOF recovery: close any unclosed frames
        while (!empty($stack)) {
            $top = array_pop($stack);
            if ($top instanceof ObjectFrame) $top->closeIfPending();
            $this->attachToParentOrSetRoot($stack, $root, $top->getValue());
        }

        return $root;
    }

    private function attachToParentOrSetRoot(array &$stack, mixed &$root, mixed $value): void {
        if ($stack === []) {
            $root = $value;
            return;
        }

        $parent = $stack[array_key_last($stack)];
        if ($parent instanceof ArrayFrame) {
            $parent->addValue($value);
        } elseif ($parent instanceof ObjectFrame) {
            $parent->addValue($value);
        }
    }

    private function valueFromToken(Token $t): mixed {
        return match ($t->type) {
            TokenType::String, TokenType::StringPartial => $t->value,
            TokenType::Number, TokenType::NumberPartial => is_numeric($t->value) ? 0 + $t->value : $t->value,
            TokenType::True => true,
            TokenType::False => false,
            TokenType::Null => null,
            default => null,
        };
    }
}