<?php declare(strict_types=1);

namespace Cognesy\Stream\Contracts;

interface Transducer
{
    public function __invoke(Reducer $reducer): Reducer;
}

