<?php declare(strict_types=1);

namespace Cognesy\Utils\Json;

/**
 * Resilient JSON decoder.
 *
 * Strategy (always in this order):
 *  1) json_decode() fast path
 *  2) Minimal repairs (trailing commas, unbalanced braces/quotes) + json_decode() retry
 *  3) Tolerant tokenizer-based single-pass parser (handles severely broken JSON)
 */
final class JsonDecoder
{
    /**
     * Decode a JSON string to a PHP value.
     * Handles valid JSON (fast), repairable JSON, and severely broken/partial JSON.
     */
    public static function decode(string $input): mixed
    {
        $trimmed = trim($input);
        if ($trimmed === '') {
            return null;
        }

        // 1) Fast path — try native json_decode first
        $sliced = self::sliceFromFirstJsonChar($trimmed);
        if ($sliced !== '') {
            try {
                return json_decode($sliced, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
            }
        }

        // 2) Minimal repairs + retry
        $repaired = self::repairCommonIssues($sliced !== '' ? $sliced : $trimmed);
        if ($repaired !== '') {
            try {
                return json_decode($repaired, true, 512, JSON_THROW_ON_ERROR);
            } catch (\Throwable) {
            }
        }

        // 3) Try extracting JSON from surrounding text
        $extracted = JsonExtractor::first($trimmed);
        if ($extracted !== null) {
            return $extracted;
        }

        // 4) Tolerant tokenizer-based parser
        return self::lenientParse($trimmed);
    }

    /**
     * Decode and guarantee array return. Returns [] on failure or non-array result.
     */
    public static function decodeToArray(string $input): array
    {
        $result = self::decode($input);
        return is_array($result) ? $result : [];
    }

    // ── Repair heuristics ─────────────────────────────────────────────

    private static function sliceFromFirstJsonChar(string $s): string
    {
        $i = strcspn($s, '{["');
        return $i < strlen($s) ? substr($s, $i) : '';
    }

    private static function repairCommonIssues(string $s): string
    {
        if ($s === '') {
            return $s;
        }

        $s = self::sliceFromFirstJsonChar($s);
        if ($s === '') {
            return '';
        }

        // Unbalanced quotes heuristic
        $quoteCount = preg_match_all('/(?<!\\\\)"/', $s);
        if (is_int($quoteCount) && $quoteCount % 2 === 1) {
            $s .= '"';
        }

        // Remove trailing commas before } or ]
        $s = preg_replace('/,(\s*[}\]])/', '$1', $s) ?? $s;

        // Balance braces
        $opens = substr_count($s, '{');
        $closes = substr_count($s, '}');
        if ($opens > $closes) {
            $s .= str_repeat('}', $opens - $closes);
        }

        // Balance brackets
        $opens = substr_count($s, '[');
        $closes = substr_count($s, ']');
        if ($opens > $closes) {
            $s .= str_repeat(']', $opens - $closes);
        }

        return $s;
    }

    // ── Tolerant tokenizer-based parser ───────────────────────────────
    // Inlined from the old LenientParser + TolerantTokenizer.

    private static function lenientParse(string $input): mixed
    {
        $tokens = self::tokenize($input);
        $pos = 0;
        $count = count($tokens);

        /** @var list<array{type: string, value: mixed, pendingKey: ?string}> $stack */
        $stack = [];
        $root = null;
        $lastTokType = null;

        while ($pos < $count) {
            [$tokType, $tokValue] = $tokens[$pos++];

            switch ($tokType) {
                case '{':
                    $stack[] = ['type' => 'object', 'value' => [], 'pendingKey' => null];
                    break;

                case '[':
                    $stack[] = ['type' => 'array', 'value' => [], 'pendingKey' => null];
                    break;

                case '}':
                case ']':
                    $completed = array_pop($stack);
                    if ($completed === null) {
                        break;
                    }
                    // Close pending key with empty value
                    if ($completed['type'] === 'object' && $completed['pendingKey'] !== null) {
                        $completed['value'][$completed['pendingKey']] = '';
                    }
                    self::attachToParentOrSetRoot($stack, $root, $completed['value']);
                    break;

                case 'string':
                case 'string_partial':
                case 'number':
                case 'number_partial':
                case 'true':
                case 'false':
                case 'null':
                    $value = match ($tokType) {
                        'true' => true,
                        'false' => false,
                        'null' => null,
                        'number', 'number_partial' => is_numeric($tokValue) ? 0 + $tokValue : $tokValue,
                        default => $tokValue,
                    };

                    if ($stack === []) {
                        $root = $value;
                        break;
                    }

                    $topIdx = array_key_last($stack);
                    $top = &$stack[$topIdx];

                    // Skip stray bareword after partial number in object context
                    if (
                        $top['type'] === 'object'
                        && $lastTokType === 'number_partial'
                        && $tokType === 'string'
                        && $top['pendingKey'] === null
                    ) {
                        $lastTokType = $tokType;
                        unset($top);
                        break;
                    }

                    if ($top['type'] === 'array') {
                        $top['value'][] = $value;
                    } elseif ($top['type'] === 'object') {
                        if ($top['pendingKey'] === null) {
                            $key = is_string($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE);
                            $top['pendingKey'] = (string) $key;
                        } else {
                            $top['value'][$top['pendingKey']] = $value;
                            $top['pendingKey'] = null;
                        }
                    }
                    unset($top);
                    break;

                case ':':
                case ',':
                    break;
            }
            $lastTokType = $tokType;
        }

        // EOF recovery — close any unclosed frames
        while (!empty($stack)) {
            $top = array_pop($stack);
            if ($top['type'] === 'object' && $top['pendingKey'] !== null) {
                $top['value'][$top['pendingKey']] = '';
            }
            self::attachToParentOrSetRoot($stack, $root, $top['value']);
        }

        return $root;
    }

    /**
     * @param list<array{type: string, value: mixed, pendingKey: ?string}> $stack
     */
    private static function attachToParentOrSetRoot(array &$stack, mixed &$root, mixed $value): void
    {
        if ($stack === []) {
            $root = $value;
            return;
        }

        $topIdx = array_key_last($stack);
        $top = &$stack[$topIdx];

        if ($top['type'] === 'array') {
            $top['value'][] = $value;
        } elseif ($top['type'] === 'object') {
            if ($top['pendingKey'] !== null) {
                $top['value'][$top['pendingKey']] = $value;
                $top['pendingKey'] = null;
            }
        }
        unset($top);
    }

    // ── Tokenizer ────────────────────────────────────────────────────

    /**
     * Tokenize JSON input tolerantly.
     * @return list<array{0: string, 1: mixed}>  Each entry: [type, value]
     */
    private static function tokenize(string $s): array
    {
        $tokens = [];
        $n = strlen($s);
        $i = 0;

        while ($i < $n) {
            // Skip whitespace
            while ($i < $n && ctype_space($s[$i])) {
                $i++;
            }
            if ($i >= $n) {
                break;
            }

            $ch = $s[$i++];

            // Structural tokens
            $single = match ($ch) {
                '{' => '{',
                '}' => '}',
                '[' => '[',
                ']' => ']',
                ':' => ':',
                ',' => ',',
                default => null,
            };
            if ($single !== null) {
                $tokens[] = [$single, null];
                continue;
            }

            // Strings
            if ($ch === '"') {
                $buf = '';
                $inBackticks = false;
                $backtickCount = 0;

                for (;;) {
                    if ($i >= $n) {
                        $tokens[] = ['string_partial', $buf];
                        break;
                    }
                    $c = $s[$i++];

                    if ($c === '`') {
                        $backtickCount++;
                        $buf .= $c;
                        if ($backtickCount === 3) {
                            $inBackticks = !$inBackticks;
                            $backtickCount = 0;
                        }
                        continue;
                    } else {
                        $backtickCount = 0;
                    }

                    if ($inBackticks) {
                        $buf .= $c;
                        continue;
                    }

                    if ($c === '"') {
                        $tokens[] = ['string', $buf];
                        break;
                    }

                    if ($c === '\\' && $i < $n) {
                        $esc = $s[$i++];
                        $buf .= match ($esc) {
                            '"', '\\', '/' => $esc,
                            'b' => "\x08",
                            'f' => "\x0C",
                            'n' => "\n",
                            'r' => "\r",
                            't' => "\t",
                            'u' => self::parseUnicodeEscape($s, $i),
                            default => $esc,
                        };
                        continue;
                    }
                    $buf .= $c;
                }
                continue;
            }

            // Numbers
            if (preg_match('/[0-9\-]/', $ch)) {
                $start = $i - 1;
                while ($i < $n && preg_match('/[0-9eE+\-.]/', $s[$i]) === 1) {
                    $i++;
                }
                $raw = substr($s, $start, $i - $start);
                $complete = ($i >= $n) || ctype_space($s[$i]) || strpbrk($s[$i], ',}]:') !== false;
                $tokens[] = [$complete ? 'number' : 'number_partial', $raw];
                continue;
            }

            // Literals / barewords
            $start = $i - 1;
            while ($i < $n && ctype_alpha($s[$i])) {
                $i++;
            }
            $word = substr($s, $start, $i - $start);
            $lw = strtolower($word);

            if ($lw === 'true') {
                $tokens[] = ['true', null];
            } elseif ($lw === 'false') {
                $tokens[] = ['false', null];
            } elseif ($lw === 'null') {
                $tokens[] = ['null', null];
            } elseif ($i >= $n) {
                // Partial literal at EOF
                if (str_starts_with('true', $lw)) {
                    $tokens[] = ['true', null];
                } elseif (str_starts_with('false', $lw)) {
                    $tokens[] = ['false', null];
                } elseif (str_starts_with('null', $lw)) {
                    $tokens[] = ['null', null];
                } else {
                    $tokens[] = ['string', $word];
                }
            } else {
                $tokens[] = ['string', $word];
            }
        }

        return $tokens;
    }

    private static function parseUnicodeEscape(string $s, int &$i): string
    {
        $hex = substr($s, $i, 4);
        if (strlen($hex) === 4 && ctype_xdigit($hex)) {
            $i += 4;
            return html_entity_decode("&#x$hex;", ENT_QUOTES, 'UTF-8');
        }
        return 'u' . $hex;
    }
}
