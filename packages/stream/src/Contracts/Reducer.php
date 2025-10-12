<?php declare(strict_types=1);

namespace Cognesy\Stream\Contracts;

interface Reducer
{
    public function init(): mixed;
    public function step(mixed $accumulator, mixed $reducible): mixed;
    public function complete(mixed $accumulator): mixed;
}