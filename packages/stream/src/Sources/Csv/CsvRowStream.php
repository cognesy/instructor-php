<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Csv;

use Cognesy\Stream\Contracts\Stream;
use Iterator;
use SplFileObject;

/**
 * @implements Stream<int, array<int, string|null>>
 */
final readonly class CsvRowStream implements Stream
{
    public function __construct(private SplFileObject $file) {}

    #[\Override]
    public function getIterator(): Iterator {
        foreach ($this->file as $row) {
            if ($row === [null] || $row === false) {
                continue;
            }
            yield $row;
        }
    }
}
