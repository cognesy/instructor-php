---
title: 'Local and HTTP agent eval targets'
docname: 'agent_eval_targets'
order: 5
id: 'ae05'
tags:
  - 'agents'
  - 'evals'
  - 'targets'
---
## Overview

Run one eval definition at two boundaries. The local target intercepts the inference driver for
fast, deterministic trajectory tests; the HTTP target exercises a deployed agent through
Instructor's owned client. The remote boundary is mocked here, so the example needs no server.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\AgentEval;
use Cognesy\Agents\Evals\AgentEvals;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\EvalResult;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\EvalVerdict;
use Cognesy\Agents\Evals\HttpAgentTarget;
use Cognesy\Agents\Evals\LocalAgentTarget;
use Cognesy\Http\Data\HttpResponse;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\HttpClient;

$local = LocalAgentTarget::fromFactory(static fn() => AgentBuilder::base()
    ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('local reply')))
    ->build());

$driver = new MockHttpDriver();
$driver->addResponse(HttpResponse::sync(201, [], '{"sessionId":"demo"}'), 'http://agent/evals/sessions', 'POST');
$driver->addResponse(
    HttpResponse::sync(200, [], '{"run":{"reply":"remote reply","status":"completed","turns":1,"tools":[],"errors":""}}'),
    'http://agent/evals/sessions/demo/turns',
    'POST',
);
$remote = new HttpAgentTarget(HttpClient::fromDriver($driver), 'http://agent', healthCheck: false);

// Define the behavior contract once, without coupling it to local or HTTP execution.
$eval = AgentEval::define(
    description: 'The agent completes a turn and returns a reply.',
    test: static function (EvalContext $t): void {
        // EvalRunner binds this context to whichever target is being evaluated.
        $t->send('hello');

        // Assert the same completion and reply behavior at either execution boundary.
        $t->succeeded();
        $t->messageIncludes('reply');
    },
)->withId('targets/portable-contract');

// A suite is reusable, so the exact same case can be run against multiple targets.
$suite = new AgentEvals($eval);

// Run once in process through the controlled inference driver.
$localResult = (new EvalRunner($local))->run($suite)->all()[0];

// Run again through the mocked HTTP deployment contract; both return EvalResult.
$remoteResult = (new EvalRunner($remote))->run($suite)->all()[0];

// Present comparable evidence and make a failed verdict fail this executable example.
$show = static function (string $target, EvalResult $result): void {
    echo "- {$target}: " . strtoupper($result->verdict()->value);
    echo " — reply=\"{$result->run()->reply()}\"\n";
    if ($result->verdict() !== EvalVerdict::Passed) {
        throw new RuntimeException("The {$target} target did not satisfy the eval contract.");
    }
};

echo "Same eval contract, different execution boundaries:\n";
$show('local driver', $localResult);
$show('HTTP deployment', $remoteResult);
?>
```
