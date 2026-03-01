<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Http;

use Cognesy\Stream\Contracts\Stream;
use Generator;
use Iterator;

/**
 * @implements Stream<int, string>
 */
final readonly class HttpEventStream implements Stream
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
                $match = [];
                if (!preg_match('/\r?\n\r?\n/', $buffer, $match, PREG_OFFSET_CAPTURE)) {
                    break;
                }

                $end = $match[0][1];
                $separatorLength = strlen($match[0][0]);
                $raw = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + $separatorLength);
                $payload = $this->extractEventData($raw);
                if ($payload !== null) {
                    yield $payload;
                }
            }
        }
        if ($buffer !== '') {
            $payload = $this->extractEventData($buffer);
            if ($payload !== null) {
                yield $payload;
            }
        }
    }

    private function extractEventData(string $raw): ?string {
        $data = [];
        foreach (explode("\n", $raw) as $line) {
            $line = rtrim($line, "\r");
            if ($line === '') {
                continue;
            }
            if ($line[0] === ':') {
                continue;
            }
            if (str_starts_with($line, 'data:')) {
                $value = substr($line, 5);
                if ($value !== '' && $value[0] === ' ') {
                    $value = substr($value, 1);
                }
                $data[] = $value;
            }
        }
        if ($data === []) {
            return null;
        }
        return implode("\n", $data);
    }
}
