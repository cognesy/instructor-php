<?php declare(strict_types=1);

namespace Cognesy\Doctor\Doctest\Events;

use Cognesy\Events\Event;

class ValidationCompleted extends Event
{
    public function __construct(
        public readonly array $results,
    ) {}
}