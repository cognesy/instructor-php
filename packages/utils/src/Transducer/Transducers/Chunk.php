<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Transducers;

use Cognesy\Utils\Transducer\Contracts\Reducer;
use Cognesy\Utils\Transducer\Contracts\Transducer;
use Cognesy\Utils\Transducer\Decorators\ChunkReducer;

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
