<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Text;

use Cognesy\Utils\Stream\Stream;
use Iterator;

/**
 * @implements Stream<int, string>
 */
final readonly class TextWordStream implements Stream
{
    public function __construct(
        private string $text,
        private string $pattern
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        return (function () {
            if ($this->text === '') {
                return;
            }
            $matches = [];
            if (preg_match_all("/{$this->pattern}/u", $this->text, $matches) === 1 || !empty($matches[0])) {
                foreach ($matches[0] as $w) {
                    yield $w;
                }
            }
        })();
    }
}
