<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Events;

use Cognesy\Events\Event;

class ValidationStarted extends Event
{
    public function __construct(
        public readonly string $target,
    ) {}
}