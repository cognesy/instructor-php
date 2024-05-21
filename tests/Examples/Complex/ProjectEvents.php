<?php
namespace Tests\Examples\Complex;

use Cognesy\Instructor\Extras\Mixin\HandlesSelfExtraction;

class ProjectEvents {
    use HandlesSelfExtraction;

    /**
     * List of events extracted from the text
     * @var \Tests\Examples\Complex\ProjectEvent[]
     */
    public array $events = [];

    /**
     * Method creates project event
     * @param string $title Title of the event
     * @param string $date Date of the event
     * @param \Tests\Examples\Complex\Stakeholder[] $stakeholders Stakeholders involved in the event
     * @return \Tests\Examples\Complex\ProjectEvent
     */
    public function createEvent(string $title, string $date, array $stakeholders): ProjectEvent {
        return new ProjectEvent();
    }
}

/**
 * Function creates project event
 * @param string $title Title of the event
 * @param string $date Date of the event
 * @param \Tests\Examples\Complex\Stakeholder[] $stakeholders Stakeholders involved in the event
 * @return \Tests\Examples\Complex\ProjectEvent
 */
function createEvent(string $title, string $date, array $stakeholders): ProjectEvent {
    return new ProjectEvent();
}
