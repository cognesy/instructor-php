---
title: 'Structured decision state and criteria'
docname: 'decision_structured_state'
id: 'a935'
tags:
  - 'no-replay'
  - 'decisions'
  - 'typesafe'
  - 'structured-state'
---
## Overview

Use JSON objects when a decision needs named facts, policies, and relationships.
Instructions and criteria can also be structured when a plain string would blur
important distinctions.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Score;

$state = JsonContent::object([
    'ticket' => [
        'message' => 'I was charged twice for order A-104. Please reverse the duplicate.',
        'customer_tier' => 'business',
    ],
    'order' => [
        'id' => 'A-104',
        'charges' => [
            ['amount_usd' => 49, 'status' => 'captured'],
            ['amount_usd' => 49, 'status' => 'captured'],
        ],
    ],
    'refund_policy' => 'Duplicate captured charges are eligible for an immediate refund.',
]);

$questions = Questions::of(
    new Choice(
        id: 'policy_action',
        options: ChoiceOptions::of(
            new ChoiceOption('refund', JsonContent::object([
                'when' => 'The policy authorizes returning the duplicate charge.',
                'excludes' => 'Cases where the charge is only pending.',
            ])),
            new ChoiceOption('investigate', 'Evidence is incomplete or contradictory.'),
            new ChoiceOption('deny', 'The policy explicitly does not cover the request.'),
        ),
        instructions: JsonContent::object([
            'task' => 'Select the action supported by `refund_policy`.',
            'evidence' => ['ticket.message', 'order.charges', 'refund_policy'],
        ]),
    ),
    new Score(
        id: 'business_impact',
        levels: ScoreLevels::of(
            JsonContent::object(['description' => 'No material customer impact.']),
            JsonContent::object(['description' => 'One customer is blocked or incorrectly charged.']),
            JsonContent::object(['description' => 'Many customers or a critical workflow are affected.']),
        ),
        instructions: 'How broad is the business impact described by `ticket` and `order`?',
    ),
);

$answers = Decision::using('typesafe')
    ->with(input: $state, questions: $questions)
    ->get();

$action = $answers->choice('policy_action');
$impact = $answers->score('business_impact');

echo "Policy action: {$action->value()}\n";
echo 'Business impact: '.number_format($impact->value(), 2)."\n";
echo 'Impact legend: '.json_encode(
    $impact->legend()->toArray(),
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
)."\n";

assert(in_array($action->value(), ['refund', 'investigate', 'deny'], true));
assert($impact->value() >= 0.0 && $impact->value() <= 2.0);
?>
```
