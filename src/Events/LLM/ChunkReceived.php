<?php
namespace Cognesy\Instructor\Events\LLM;

use Cognesy\Instructor\Events\Event;

class ChunkReceived extends Event
{
    public function __construct(
        public string $chunk = '',
    ) {}
}