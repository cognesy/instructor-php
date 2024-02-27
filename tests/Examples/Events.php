<?php

namespace Tests\Examples;

use Cognesy\Instructor\Traits\ExtractableMixin;

class Events {
    use ExtractableMixin;

    /**
     * List of events extracted from the text
     * @var \Tests\Examples\Event[]
     */
    public array $events = [];

    /**
     * Method creates project event
     * @param string $title Title of the event
     * @param string $date Date of the event
     * @param \Tests\Examples\Stakeholder[] $stakeholders Stakeholders involved in the event
     * @return \Tests\Examples\Event
     */
    public function createEvent(string $title, string $date, array $stakeholders): Event {
        return new Event();
    }
}

/**
 * Function creates project event
 * @param string $title Title of the event
 * @param string $date Date of the event
 * @param \Tests\Examples\Stakeholder[] $stakeholders Stakeholders involved in the event
 * @return \Tests\Examples\Event
 */
function createEvent(string $title, string $date, array $stakeholders): Event {
    return new Event();
}
