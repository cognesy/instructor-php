<?php

namespace Cognesy\Instructor\Core\Factories;

use Cognesy\Instructor\Events\EventDispatcher;
use Cognesy\Instructor\Stream;
use JetBrains\PhpStorm\Deprecated;

#[Deprecated('Not used - may be removed in the future.')]
class StreamFactory
{
    public function __construct(
        private EventDispatcher $eventDispatcher,
    ) {}

    public function create(iterable $stream) : Stream {
        return new Stream($stream, $this->eventDispatcher);
    }
}
