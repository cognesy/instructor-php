<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Text;

use Cognesy\Stream\Contracts\Stream;
use Iterator;

/**
 * @implements Stream<int, string>
 */
final readonly class WordStream implements Stream
{
    public function __construct(
        private Stream $lines,
        private string $pattern
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        foreach ($this->lines as $line) {
            $matches = [];
            $matchCount = preg_match_all("/{$this->pattern}/u", (string)$line, $matches);
            if ($matchCount > 0 && !empty($matches[0])) {
                foreach ($matches[0] as $w) {
                    yield $w;
                }
            }
        }
    }
}
