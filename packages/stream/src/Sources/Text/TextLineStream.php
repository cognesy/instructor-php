<?php declare(strict_types=1);

namespace Cognesy\Stream\Sources\Text;

use Cognesy\Stream\Contracts\Stream;
use Iterator;

/**
 * @implements Stream<int, string>
 */
final readonly class TextLineStream implements Stream
{
    public function __construct(
        private string $text,
        private string $eol,
        private bool $dropEmpty
    ) {}

    #[\Override]
    public function getIterator(): Iterator {
        if ($this->text === '') {
            if (!$this->dropEmpty) {
                yield '';
            }
            return;
        }
        if ($this->eol === '') {
            throw new \InvalidArgumentException('EOL separator cannot be empty');
        }
        $parts = explode($this->eol, $this->text);
        foreach ($parts as $part) {
            if ($this->dropEmpty && $part === '') {
                continue;
            }
            yield $part;
        }
    }
}
