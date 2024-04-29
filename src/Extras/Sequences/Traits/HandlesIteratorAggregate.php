<?php

namespace Cognesy\Instructor\Extras\Sequences\Traits;

use ArrayIterator;
use Traversable;

trait HandlesIteratorAggregate
{
    public function getIterator() : Traversable {
        return new ArrayIterator($this->list);
    }
}