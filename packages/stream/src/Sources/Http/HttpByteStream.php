<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Http;

use Cognesy\Stream\Contracts\Stream;
use Generator;
use Iterator;

/**
 * @implements Stream<int, string>
 */
final readonly class HttpByteStream implements Stream
{
    /** @var Generator<string> */
    private Generator $chunks;

    /**
     * @param Generator<string> $chunks
     */
    public function __construct(Generator $chunks) {
        $this->chunks = $chunks;
    }

    #[\Override]
    public function getIterator(): Iterator {
        foreach ($this->chunks as $chunk) {
            $len = strlen($chunk);
            for ($i = 0; $i < $len; $i++) {
                yield $chunk[$i];
            }
        }
    }
}
