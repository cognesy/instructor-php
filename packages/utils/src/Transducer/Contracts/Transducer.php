<?php declare(strict_types=1);

namespace Cognesy\Utils\Transducer\Contracts;

interface Transducer
{
    public function __invoke(Reducer $reducer): Reducer;
}

