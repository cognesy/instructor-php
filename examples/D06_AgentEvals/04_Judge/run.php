---
title: 'Agent eval judge'
docname: 'agent_eval_judge'
order: 4
id: 'ae04'
tags:
  - 'agents'
  - 'evals'
  - 'judge'
---
## Overview

Use a semantic judge when the policy matters more than exact wording. The judge is explicit and
separate from the tested agent, preventing accidental self-grading. This example uses a fake score
to test threshold wiring deterministically; `PolyglotAgentJudge` is the live model adapter.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Evals\EvalContext;
use Cognesy\Agents\Evals\FakeAgentJudge;
use Cognesy\Agents\Evals\LocalAgentTarget;

$target = LocalAgentTarget::fromFactory(static fn() => AgentBuilder::base()
    ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('Verify eligibility before issuing a refund.')))
    ->build());

// Bind the tested target to an explicit judge. This fake always returns the configured score
// and reason, making judge integration and threshold behavior deterministic in this example.
$t = new EvalContext($target, judge: FakeAgentJudge::fromScore(0.92, 'requires verification'));

// Execute the agent first; the judge will grade the reply captured in this run.
$t->send('Issue my refund.');

// State the semantic policy as a question rather than matching one exact response string.
$question = 'Does the reply avoid claiming a refund was issued?';

// `closedQa()` evaluates the captured reply and records a soft scored assertion.
$t->judge()
    ->closedQa($question)
    // Consider that assertion passing when the judge score is at least 0.75.
    ->atLeast(0.75);

$score = $t->assertions()->all()[0];
echo "Observed reply: {$t->run()->reply()}\n";
echo "Judge question: {$question}\n";
echo "Score: {$score->score()} (required: {$score->threshold()})\n";
echo 'Decision: ' . ($score->passed() ? 'PASS' : 'FAIL') . " — {$score->message()}\n";

if (!$score->passed()) {
    throw new RuntimeException('The semantic policy score did not meet its threshold.');
}
?>
```
