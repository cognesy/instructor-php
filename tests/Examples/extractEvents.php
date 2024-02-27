<?php
namespace Tests\Examples\Events;

use Tests\Examples\Event;

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