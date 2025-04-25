<?php

namespace Cognesy\Instructor\Tests\Examples\Events;

use Cognesy\Instructor\Tests\Examples\Complex\ProjectEvent;
use Cognesy\Instructor\Tests\Examples\Complex\Stakeholder;

if (!function_exists('createEvent')) {
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
}
