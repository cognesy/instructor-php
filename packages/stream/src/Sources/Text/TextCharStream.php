<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Text;

use Cognesy\Stream\Contracts\Stream;
use Iterator;

/**
 * @implements Stream<int, string>
 */
final readonly class TextCharStream implements Stream
{
    public function __construct(private string $text) {}

    #[\Override]
    public function getIterator(): Iterator {
        $len = strlen($this->text);
        for ($i = 0; $i < $len; $i++) {
            yield $this->text[$i];
        }
    }
}
