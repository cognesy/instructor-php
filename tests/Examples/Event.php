<?php

namespace Tests\Examples;

use Cognesy\Instructor\Attributes\Description;

/** Represents a project event */
class Event {
    /** Title of the event */
    #[Description('This should be a short, descriptive title of the event')]
    public string $title = '';
    /** Concise, informative description of the event */
    public string $description = '';
    /** Type of the event */
    public EventType $type = EventType::Other;
    /** Status of the event */
    public EventStatus $status = EventStatus::Unknown;
    /** Stakeholders involved in the event */
    /** @var \Tests\Examples\Stakeholder[] */
    public array $stakeholders = [];
    /** Date of the event if reported in the text */
    public ?string $date = '';
}
