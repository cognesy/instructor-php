<?php

namespace Cognesy\Instructor\Core;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Stream;

class StreamFactory
{
    public function __construct(
        private EventDispatcher $eventDispatcher,
    ) {}

    public function create(iterable $stream) : Stream {
        return new Stream($stream, $this->eventDispatcher);
    }
}
