<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Filesystem;

use Cognesy\Stream\Contracts\Stream;
use Iterator;
use SplFileObject;

/**
 * @implements Stream<int, string>
 */
final readonly class FileChunkStream implements Stream
{
    public function __construct(
        private SplFileObject $file,
        private int $size
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        $this->file->rewind();
        while (!$this->file->eof()) {
            $chunk = $this->file->fread($this->size);
            if ($chunk === '' || $chunk === false) {
                break;
            }
            yield $chunk;
        }
    }
}
