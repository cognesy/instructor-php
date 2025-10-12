<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Filesystem;

use Cognesy\Utils\Stream\Stream;
use Iterator;
use SplFileObject;

/**
 * @implements Stream<int, string>
 */
final readonly class FileLineStream implements Stream
{
    public function __construct(
        private SplFileObject $file,
        private bool $dropEmpty
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        return (function () {
            $orig = $this->file->getFlags();
            $this->file->setFlags(SplFileObject::DROP_NEW_LINE);
            try {
                $this->file->rewind();
                foreach ($this->file as $line) {
                    if (!is_string($line)) {
                        continue;
                    }
                    if ($this->dropEmpty && $line === '') {
                        continue;
                    }
                    yield $line;
                }
            } finally {
                $this->file->setFlags($orig);
            }
        })();
    }
}
