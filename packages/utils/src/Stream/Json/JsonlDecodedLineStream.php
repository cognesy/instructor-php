<?php declare(strict_types=1);

namespace Cognesy\Utils\Stream\Json;

use Cognesy\Utils\Stream\Stream;
use Iterator;

/**
 * @implements Stream<int, mixed>
 */
final readonly class JsonlDecodedLineStream implements Stream
{
    public function __construct(
        private Stream $rawLineStream,
        private bool $assoc
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        return (function () {
            foreach ($this->rawLineStream as $line) {
                $decoded = json_decode($line, $this->assoc);
                if (json_last_error() !== JSON_ERROR_NONE) {
                    continue;
                }
                yield $decoded;
            }
        })();
    }
}
