<?php
namespace Tests\Examples\Complex;

use Cognesy\Instructor\Extras\Mixin\HandlesSelfInference;

class ProjectEvents {
    use HandlesSelfInference;

    /**
     * List of events extracted from the text
     * @var ProjectEvent[]
     */
    public array $events = [];

    /**
     * Method creates project event
     * @param string $title Title of the event
     * @param string $date Date of the event
     * @param Stakeholder[] $stakeholders Stakeholders involved in the event
     * @return ProjectEvent
     */
    public function createEvent(string $title, string $date, array $stakeholders): ProjectEvent {
        return new ProjectEvent();
    }
}

/**
 * Function creates project event
 * @param string $title Title of the event
 * @param string $date Date of the event
 * @param Stakeholder[] $stakeholders Stakeholders involved in the event
 * @return ProjectEvent
 */
function createEvent(string $title, string $date, array $stakeholders): ProjectEvent {
    return new ProjectEvent();
}
