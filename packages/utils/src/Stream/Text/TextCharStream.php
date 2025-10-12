<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Text;

use Cognesy\Utils\Stream\Stream;
use Iterator;

/**
 * @implements Stream<int, string>
 */
final readonly class TextCharStream implements Stream
{
    public function __construct(private string $text) {}

    #[\Override]
    public function getIterator(): Iterator {
        return (function () {
            $len = strlen($this->text);
            for ($i = 0; $i < $len; $i++) {
                yield $this->text[$i];
            }
        })();
    }
}
