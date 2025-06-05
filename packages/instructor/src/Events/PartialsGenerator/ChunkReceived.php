<?php
namespace Cognesy\Instructor\Events\PartialsGenerator;

use Cognesy\Utils\Events\Event;

final class ChunkReceived extends Event
{
    public function __construct(
        public string $chunk = '',
    ) {
        parent::__construct();
    }

    public function __toString(): string {
        return '`'.$this->chunk.'`';
    }
}