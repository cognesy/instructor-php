<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Http;

use Cognesy\Stream\Contracts\Stream;
use Generator;
use Iterator;

/**
 * @implements Stream<int, string>
 */
final readonly class HttpLineStream implements Stream
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
        $buffer = '';
        foreach ($this->chunks as $chunk) {
            if ($chunk === '') {
                continue;
            }
            $buffer .= $chunk;
            while (true) {
                $pos = strpos($buffer, "\n");
                if ($pos === false) {
                    break;
                }
                $line = substr($buffer, 0, $pos);
                $buffer = substr($buffer, $pos + 1);
                if ($line !== '' && $line[-1] === "\r") {
                    $line = substr($line, 0, -1);
                }
                yield $line;
            }
        }
        if ($buffer !== '') {
            if ($buffer !== '' && $buffer[-1] === "\r") {
                $buffer = substr($buffer, 0, -1);
            }
            yield $buffer;
        }
    }
}
