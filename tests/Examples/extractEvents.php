<?php
namespace Tests\Examples\Events;

use Tests\Examples\Complex\ProjectEvent;

if (!function_exists('createEvent')) {
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
}
