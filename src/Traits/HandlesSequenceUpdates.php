<?php

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Events\Request\SequenceUpdated;

trait HandlesSequenceUpdates
{
    protected $onSequenceUpdate;

    /**
     * Listens to sequence updates
     */
    public function onSequenceUpdate(callable $listener) : self {
        $this->onSequenceUpdate = $listener;
        $this->events->addListener(
            SequenceUpdated::class,
            $this->handleSequenceUpdate(...)
        );
        return $this;
    }

    /**
     * Provides sequence instead of event - for developer convenience
     */
    private function handleSequenceUpdate(SequenceUpdated $event) : void {
        if (!is_null($this->onSequenceUpdate)) {
            ($this->onSequenceUpdate)($event->sequence);
        }
    }
}