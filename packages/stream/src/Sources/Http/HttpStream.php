<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Http;

use Cognesy\Stream\Contracts\Stream;
use Generator;

/**
 * Facade for transforming an HTTP chunk stream (Generator<string>)
 * into specific stream views.
 */
final readonly class HttpStream
{
    /** @var Generator<string> */
    private Generator $chunks;

    private function __construct(Generator $chunks) {
        $this->chunks = $chunks;
    }

    /**
     * @param Generator<string> $chunks
     */
    public static function from(Generator $chunks): self {
        return new self($chunks);
    }

    /**
     * Stream each byte as a 1-byte string.
     * Note: the underlying generator is consumed once.
     *
     * @return Stream<int, string>
     */
    public function bytes(): Stream {
        return new HttpByteStream($this->chunks);
    }

    /**
     * Stream lines delimited by "\n" (strips trailing "\r").
     * Note: the underlying generator is consumed once.
     *
     * @return Stream<int, string>
     */
    public function lines(): Stream {
        return new HttpLineStream($this->chunks);
    }

    /**
     * Stream Server-Sent Events (SSE) by parsing the incoming byte stream
     * into discrete events separated by a blank line. Returns payload built
     * from concatenated data: lines, separated with "\n".
     * Note: the underlying generator is consumed once.
     *
     * @return Stream<int, string>
     */
    public function events(): Stream {
        return new HttpEventStream($this->chunks);
    }
}

