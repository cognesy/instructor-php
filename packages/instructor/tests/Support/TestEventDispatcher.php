<?php declare(strict_types=1);

namespace Tests\Instructor\Support;

use Psr\EventDispatcher\EventDispatcherInterface;

class TestEventDispatcher implements EventDispatcherInterface
{
    /** @var array<int, object> */
    public array $events = [];

    public function dispatch(object $event): object
    {
        $this->events[] = $event;
        return $event;
    }
}

