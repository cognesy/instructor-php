# Extraction of complex objects (Anthropic)

This is an example of extraction of a very complex structure from
the provided text with Anthropic Claude 3 Opus model.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

$report = <<<'EOT'
    [2021-09-01]
    Acme Insurance project to implement SalesTech CRM solution is currently in RED status due to delayed delivery of document production system, led by 3rd party vendor - Alfatech. Customer (Acme) is discussing the resolution with the vendor. Due to dependencies it will result in delay of the ecommerce track by 2 sprints. System integrator (SysCorp) are working to absorb some of the delay by deploying extra resources to speed up development when the doc production is done. Another issue is that the customer is not able to provide the test data for the ecommerce track. SysCorp notified it will impact stabilization schedule unless resolved by the end of the month. Steerco has been informed last week about the potential impact of the issues, but insists on maintaining release schedule due to marketing campaign already ongoing. Customer executives are asking us - SalesTech team - to confirm SysCorp's assessment of the situation. We're struggling with that due to communication issues - SysCorp team has not shown up on 2 recent calls. Lack of insight has been escalated to SysCorp's leadership team yesterday, but we've got no response yet. The previously reported Integration Proxy connectivity issue which was blocking policy track has been resolved on 2021-08-30 - the track is now GREEN. Production deployment plan has been finalized on Aug 15th and awaiting customer approval.
    EOT;

class ProjectEvents {
    /**
     * List of events extracted from the text
     * @var ProjectEvent[]
     */
    public array $events = [];
}

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
    public ?string $details = '';
}

enum StakeholderRole: string {
    case Customer = 'customer';
    case Vendor = 'vendor';
    case SystemIntegrator = 'system integrator';
    case Other = 'other';
}

// Create instance of client initialized with custom parameters
$client = new AnthropicClient(
    apiKey: Env::get('ANTHROPIC_API_KEY'),
    requestTimeout: 90,
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: $report,
    responseModel: ProjectEvents::class,
    model: 'claude-3-opus-20240229',
    mode: Mode::Json,
    options: ['max_tokens' => 2048 ]
);

print("Completed response model:\n\n");
dump($user);
?>
```
