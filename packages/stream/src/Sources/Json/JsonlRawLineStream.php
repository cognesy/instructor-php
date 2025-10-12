<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Json;

use Cognesy\Stream\Contracts\Stream;
use Iterator;
use SplFileObject;

/**
 * @implements Stream<int, string>
 */
final readonly class JsonlRawLineStream implements Stream
{
    public function __construct(private SplFileObject $file) {}

    #[\Override]
    public function getIterator(): Iterator {
        foreach ($this->file as $line) {
            if (!is_string($line) || $line === '') {
                continue;
            }
            yield $line;
        }
    }
}
