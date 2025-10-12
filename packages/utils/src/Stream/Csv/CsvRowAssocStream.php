<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Csv;

use Cognesy\Utils\Stream\Stream;
use Iterator;
use SplFileObject;

/**
 * @implements Stream<int, array<string, string|null>>
 */
final readonly class CsvRowAssocStream implements Stream
{
    public function __construct(private SplFileObject $file) {}

    #[\Override]
    public function getIterator(): Iterator {
        return (function () {
            $headers = null;
            foreach ($this->file as $row) {
                if ($row === [null] || $row === false || !is_array($row)) {
                    continue;
                }
                if ($headers === null) {
                    $headers = $row;
                    continue;
                }
                $assoc = [];
                $count = min(count($headers), count($row));
                for ($i = 0; $i < $count; $i++) {
                    $assoc[(string)$headers[$i]] = $row[$i] ?? null;
                }
                yield $assoc;
            }
        })();
    }
}
