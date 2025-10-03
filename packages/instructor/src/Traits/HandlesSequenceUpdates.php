<?php declare(strict_types=1);

namespace Cognesy\Instructor\Traits;

use Cognesy\Instructor\Events\Request\SequenceUpdated;

trait HandlesSequenceUpdates
{
    /** @var callable(object): void|null */
    protected $onSequenceUpdate = null;

    /**
     * Listens to sequence updates
     *
     * @param callable(object): void $listener
     */
    public function onSequenceUpdate(callable $listener) : static {
        $this->onSequenceUpdate = $listener;
        $this->events->addListener(
            SequenceUpdated::class,
            $this->handleSequenceUpdate(...)
        );
        return $this;
    }

    // INTERNAL ////////////////////////////////////////////////////////////////////

    /**
     * Provides sequence instead of event - for developer convenience
     */
    private function handleSequenceUpdate(SequenceUpdated $event) : void {
        if (!is_null($this->onSequenceUpdate)) {
            ($this->onSequenceUpdate)($event->sequence);
        }
    }
}