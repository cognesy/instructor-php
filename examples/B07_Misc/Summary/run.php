---
title: 'Chain of Summaries'
docname: 'chain_of_summaries'
---

## Overview

This is an example of a simple summarization.

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Features\LLM\Inference;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Debug\Debug;

$report = <<<EOT
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

class Summary {
    public string $summary = '';
    public array $keywords = [];
}

$summary = (new Instructor)
    ->withConnection('openai')
    ->withCachedContext(messages: $report)
    ->request(
        responseModel: Summary::class,
        prompt: 'Create a condensed, 2-3 sentence summary of the project status',
        options: [
            'max_tokens' => 256,
        ],
    )
    ->get();

dump($summary);
?>
```
