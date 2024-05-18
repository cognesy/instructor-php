# Chain of Summaries

This is an example of summarization with increasing amount of details.
Instructor is provided with data structure containing instructions on how to
create increasingly detailed summaries of the project report.

It starts with generating an overview of the project, followed by X iterations
of increasingly detailed summaries. Each iteration should contain all the
information from the previous summary, plus a few additional facts from the
content which are most relevant and missing from the previous iteration.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

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

/** Executive level summary of the project */
class Summary {
    /** current summary iteration, not bigger than 3 */
    public int $iteration;
    /** @var string[] 1-3 facts most relevant from executive perspective and missing from the summary (avoid technical details) */
    public array $missingFacts;
    /** denser summary in 1-3 sentences, which covers every fact from the previous summary plus the missing ones */
    public string $expandedSummary;
}

/** Increasingly denser, expanded summaries */
class ChainOfSummaries {
    /** simplified, executive view with no details, just a single statement of overall situation */
    public string $overview;
    /** @var Summary[] contains at least 3 gradually more expanded summaries of the content */
    public array $summaries;
}

$schema = (new Instructor)->createJsonSchema(ChainOfSummaries::class);
dd($schema);

$summaries = (new Instructor)
    ->request(
        messages: $report,
        responseModel: ChainOfSummaries::class,
        options: [
            'max_tokens' => 4096,
        ])
    ->get();

print("\n# Summaries with increasing density:\n\n");
print("Overview:\n");
print("{$summaries->overview}\n\n");
foreach ($summaries->summaries as $summary) {
    print("Expanded summary - iteration #{$summary->iteration}:\n");
    print("{$summary->expandedSummary}\n\n");
}
