<?php declare(strict_types=1);

namespace Cognesy\Stream\Transducers;

use Cognesy\Stream\Contracts\Reducer;
use Cognesy\Stream\Contracts\Transducer;
use Cognesy\Stream\Decorators\ChunkReducer;

final readonly class Chunk implements Transducer
{
    public function __construct(private int $size) {
        if ($size <= 0) {
            throw new \InvalidArgumentException("Size must be greater than 0");
        }
    }

    #[\Override]
    public function __invoke(Reducer $reducer): Reducer {
        return new ChunkReducer($this->size, $reducer);
    }
}
