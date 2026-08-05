---
title: 'Running agent eval suites'
docname: 'running_agent_evals'
order: 7
id: 'ae07'
tags:
  - 'agents'
  - 'evals'
  - 'runner'
---
## Overview

Select a focused lane by ID and tags while preserving suite order. The same scored result can be
advisory during development (exit 0) and gating in strict CI (exit 1), without changing the eval.

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
use Cognesy\Agents\Evals\EvalExitCode;
use Cognesy\Agents\Evals\EvalRunOptions;
use Cognesy\Agents\Evals\EvalRunner;
use Cognesy\Agents\Evals\EvalTags;
use Cognesy\Agents\Evals\LocalAgentTarget;

// Build an offline target whose fake inference driver always replies "ok".
$target = LocalAgentTarget::fromFactory(static fn() => AgentBuilder::base()
    ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('ok')))
    ->build());

// Define a smoke case whose failed soft check records a score without failing advisory runs.
$quality = AgentEval::define(
    description: 'Tracks a non-gating quality score.',
    tags: EvalTags::of('smoke'),
    test: static function (EvalContext $t): void {
        // Execute one agent turn so its completion state can be evaluated.
        $t->send('check quality');
        // Require the selected agent turn to complete successfully.
        $t->succeeded();
        // Record an intentionally failed soft check, producing a scored verdict instead of a failed gate.
        $t->check('quality', false)->soft();
    },
)->withId('smoke/quality');

// Define a failing regression case that proves cases outside the selected lane are not executed.
$regression = AgentEval::define(
    description: 'A regression case outside the selected lane.',
    tags: EvalTags::of('regression'),
    test: static function (EvalContext $t): void {
        // Fail deliberately if filtering ever allows this regression case to run.
        $t->check('must-not-run', false);
    },
)->withId('regression/not-selected');

// Define a passing smoke gate that appears after the scored case in the selected suite.
$safety = AgentEval::define(
    description: 'A passing smoke safety gate.',
    tags: EvalTags::of('smoke'),
    test: static function (EvalContext $t): void {
        // Execute the safety-check turn through the same deterministic target.
        $t->send('check safety');
        // Require the safety-check turn to complete successfully.
        $t->succeeded();
    },
)->withId('smoke/safety');

// Select cases whose IDs match smoke/* and whose tags include smoke; both conditions must match.
$options = EvalRunOptions::default()
    ->withFilter('smoke/*')
    ->withTags(EvalTags::of('smoke'));

$result = (new EvalRunner($target))->run(
    new AgentEvals($quality, $regression, $safety),
    $options,
);

echo "Selected lane, in suite order:\n";
foreach ($result as $case) {
    echo "- {$case->id()}: {$case->verdict()->value}\n";
}
echo 'Advisory exit code: ' . $result->exitCode(false)->value . "\n";
echo 'Strict CI exit code: ' . $result->exitCode(true)->value . "\n";

if ($result->count() !== 2
    || $result->exitCode(false) !== EvalExitCode::Success
    || $result->exitCode(true) !== EvalExitCode::EvalFailure
) {
    throw new RuntimeException('Filtering or strict exit-code behavior did not match the suite policy.');
}
?>
```
