<?php

namespace Cognesy\Instructor\Traits\Instructor;

use Cognesy\Instructor\Events\PartialsGenerator\PartialResponseGenerated;

trait HandlesPartialUpdates
{
    protected $onPartialResponse;

    /**
     * Listens to partial responses
     *
     * @param-later-invoked-callable $listener
     */
    public function onPartialUpdate(callable $listener) : self {
        $this->onPartialResponse = $listener;
        $this->events->addListener(
            PartialResponseGenerated::class,
            $this->handlePartialResponse(...)
        );
        return $this;
    }

    /**
     * Provides partial response instead of event - for developer convenience
     */
    private function handlePartialResponse(PartialResponseGenerated $event) : void {
        if (!is_null($this->onPartialResponse)) {
            ($this->onPartialResponse)($event->partialResponse);
        }
    }
}