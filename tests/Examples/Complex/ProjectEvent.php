<?php

namespace Tests\Examples\Complex;

use Cognesy\Instructor\Attributes\Description;

/** Represents a project event */
class ProjectEvent {
    /** Title of the event */
    #[Description('This should be a short, descriptive title of the event')]
    public string $title = '';
    /** Concise, informative description of the event */
    public string $description = '';
    /** Type of the event */
    public ProjectEventType $type = ProjectEventType::Other;
    /** Status of the event */
    public ProjectEventStatus $status = ProjectEventStatus::Unknown;
    /** Stakeholders involved in the event */
    /** @var \Tests\Examples\Complex\Stakeholder[] */
    public array $stakeholders = [];
    /** Date of the event if reported in the text */
    public ?string $date = '';
}
