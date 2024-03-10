<?php

namespace Cognesy\Instructor\Extras\Sequence;

use Iterator;
use IteratorAggregate;
use Traversable;

class Sequence implements Sequenceable, IteratorAggregate
{
    private string $class;
    private Iterator $iterator;

    private function __construct(string $class) {
        $this->class = $class;
    }

    public static function of(string $class) : Sequenceable {
        return new self($class);
    }

    //////////////////////////////////////////////////////////////////////

    // when asked for schema - return $class[]

    //////////////////////////////////////////////////////////////////////

    public function getIterator(): Traversable
    {
        return $this->iterator;
    }

    public function setIterator(Iterator $iterator) {
        $this->iterator = $iterator;
    }
}
