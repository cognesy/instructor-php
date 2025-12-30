<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

final class MultiJsonExtractor
{
    /**
     * @return ParsedJsonFragment|array<ParsedJsonFragment>|null
     */
    public static function extract(string $text, MultiJsonStrategy $strategy): mixed
    {
        $results = [];
        $len = strlen($text);
        $i = 0;

        while ($i < $len) {
            $start = self::findNextJsonStart($text, $i);
            if ($start === -1) break;

            $parsed = self::parseFrom($text, $start);
            if ($strategy === MultiJsonStrategy::StopOnFirst) return $parsed;
            $results[] = $parsed;
            if ($strategy === MultiJsonStrategy::StopOnLast) {
                // continue scanning to possibly replace with a later one
                $i = max($parsed->endIndex, $start + 1);
                continue;
            }
            // ParseAll: move past this fragment
            $i = max($parsed->endIndex, $start + 1);
        }

        return match ($strategy) {
            MultiJsonStrategy::StopOnLast => empty($results) ? null : end($results),
            MultiJsonStrategy::ParseAll   => $results,
            MultiJsonStrategy::StopOnFirst => null,
        };
    }

    private static function findNextJsonStart(string $s, int $from): int
    {
        $n = strlen($s); $i = $from;
        $inFence = false; $fenceChar = '`';

        while ($i < $n) {
            $ch = $s[$i];

            // Skip fenced code blocks ```...```
            if ($ch === $fenceChar) {
                $run = 1; $j = $i + 1;
                while ($j < $n && $s[$j] === $fenceChar) { $run++; $j++; }
                if ($run >= 3) {
                    /** @phpstan-ignore-next-line */
                    $inFence = !$inFence;
                    $i = $j;
                    continue;
                }
            }

            /** @phpstan-ignore-next-line */
            if (!$inFence && ($ch === '{' || $ch === '[')) {
                return $i;
            }
            $i++;
        }
        return -1;
    }

    private static function parseFrom(string $s, int $start): ParsedJsonFragment
    {
        // 1) Try native + minimal repair on the slice starting at $start
        $slice = substr($s, $start);
        $value = ResilientJson::parse($slice);

        // 2) Determine how far tokenizer progressed to compute end index
        //    We use the tolerant tokenizer to advance until the parser
        //    considers the root closed (or EOF).
        $tokenizer = new TolerantTokenizer($slice);
        $stackDepth = 0; $opened = false;

        while ($tok = $tokenizer->next()) {
            $t = $tok->type;
            if ($t === TokenType::LeftBrace || $t === TokenType::LeftBracket) { $stackDepth++; $opened = true; }
            elseif ($t === TokenType::RightBrace || $t === TokenType::RightBracket) { if ($stackDepth > 0) $stackDepth--; }
            // stop heuristic: we entered a JSON root and returned to depth 0
            if ($opened && $stackDepth === 0) break;
        }

        // Need tokenizer index: expose it (see below) and compute end
        $end = $start + self::getTokenizerIndex($tokenizer);
        return new ParsedJsonFragment($value, $start, $end);
    }

    private static function getTokenizerIndex(TolerantTokenizer $t): int
    {
        // Add a tiny accessor in TolerantTokenizer:
        // public function index(): int { return $this->index; }
        return $t->index();
    }
}