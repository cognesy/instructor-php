<?php
namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Instructor\Events\Event;

class ChunkReceived extends Event
{
    public function __construct(
        public string $chunk = '',
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return $this->chunk;
    }
}