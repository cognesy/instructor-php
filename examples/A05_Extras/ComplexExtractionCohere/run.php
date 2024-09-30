---
title: 'Extraction of complex objects (Cohere)'
docname: 'complex_extraction_cohere'
---

## Overview

This is an example of extraction of a very complex structure from
the provided text with Cohere R models.

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\Instructor;

$report = <<<'EOT'
    [2021-09-01]
    Acme Insurance project to implement SalesTech CRM solution is currently
    in RED status due to delayed delivery of document production system, led
    by 3rd party vendor - Alfatech. Customer (Acme) is discussing the resolution
    with the vendor. Due to dependencies it will result in delay of the
    ecommerce track by 2 sprints. System integrator (SysCorp) are working
    to absorb some of the delay by deploying extra resources to speed up
    development when the doc production is done. Another issue is that the
    customer is not able to provide the test data for the ecommerce track.
    SysCorp notified it will impact stabilization schedule unless resolved by
    the end of the month. Steerco has been informed last week about the
    potential impact of the issues, but insists on maintaining release schedule
    due to marketing campaign already ongoing. Customer executives are asking
    us - SalesTech team - to confirm SysCorp's assessment of the situation.
    We're struggling with that due to communication issues - SysCorp team has
    not shown up on 2 recent calls. Lack of insight has been escalated to
    SysCorp's leadership team yesterday, but we've got no response yet. The
    previously reported Integration Proxy connectivity issue which was blocking
    policy track has been resolved on 2021-08-30 - the track is now GREEN.
    Production deployment plan has been finalized on Aug 15th and awaiting
    customer approval.
    EOT;

echo "Extracting project events from the report:\n\n";
echo $report . "\n\n";

/** Represents a project event */
class ProjectEvent {
    /** Title of the event - this should be a short, descriptive title of the event */
    public string $title = '';
    /** Concise, informative description of the event */
    public string $description = '';
    /** Type of the event */
    public ProjectEventType $type = ProjectEventType::Other;
    /** Status of the event */
    public ProjectEventStatus $status = ProjectEventStatus::Unknown;
    /** Stakeholders involved in the event */
    /** @var Stakeholder[] */
    public array $stakeholders = [];
    /** Date of the event if reported in the text */
    public ?string $date = '';
}

/** Represents status of project event */
enum ProjectEventStatus: string {
    case Open = 'open';
    case Closed = 'closed';
    case Unknown = 'unknown';
}

/** Represents type of project event */
enum ProjectEventType: string {
    case Risk = 'risk';
    case Issue = 'issue';
    case Action = 'action';
    case Progress = 'progress';
    case Other = 'other';
}

/** Represents a project stakeholder */
class Stakeholder {
    /** Name of the stakeholder */
    public string $name = '';
    /** Role of the stakeholder, if specified */
    public StakeholderRole $role = StakeholderRole::Other;
    /** Any details on the stakeholder, if specified - any mentions of company, organization, structure, group, team, function */
    public string $details = '';
}

enum StakeholderRole: string {
    case Customer = 'customer';
    case Vendor = 'vendor';
    case SystemIntegrator = 'system integrator';
    case Other = 'other';
}

$instructor = (new Instructor)->withConnection('cohere1');

echo "PROJECT EVENTS:\n\n";

$events = $instructor
    ->onSequenceUpdate(fn($sequence) => displayEvent($sequence->last()))
    ->request(
        messages: $report,
        responseModel: Sequence::of(ProjectEvent::class),
        model: 'command-r-plus-08-2024',
        mode: Mode::JsonSchema,
        options: [
            'max_tokens' => 2048,
            'stream' => true,
        ])
    ->get();

echo "TOTAL EVENTS: " . count($events) . "\n";

function displayEvent(ProjectEvent $event) : void {
    echo "Event: {$event->title}\n";
    echo " - Descriptions: {$event->description}\n";
    echo " - Type: {$event->type->value}\n";
    echo " - Status: {$event->status->value}\n";
    echo " - Date: {$event->date}\n";
    if (empty($event->stakeholders)) {
        echo " - Stakeholders: none\n";
    } else {
        echo " - Stakeholders:\n";
        foreach($event->stakeholders as $stakeholder) {
            echo "   - {$stakeholder->name} ({$stakeholder->role->value})\n";
        }
    }
    echo "\n";
}
?>
```
