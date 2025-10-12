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
                $end = strpos($buffer, "\n\n");
                if ($end === false) {
                    break;
                }
                $raw = substr($buffer, 0, $end);
                $buffer = substr($buffer, $end + 2);
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
