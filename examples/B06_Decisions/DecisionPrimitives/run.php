---
title: 'Typed decision primitives'
docname: 'decision_primitives'
id: '2509'
tags:
  - 'no-replay'
  - 'decisions'
  - 'typesafe'
  - 'noul'
  - 'choice'
  - 'score'
---
## Overview

Ask several independent questions about the same state in one request. Each
question returns its own strict answer type rather than generated prose.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

$questions = Questions::of(
    new Noul(
        id: 'refund_requested',
        instructions: 'Does the customer explicitly request a refund?',
        criteria: new NoulCriteria(
            true: 'The customer asks for money to be returned.',
            false: 'The customer does not ask for money to be returned.',
        ),
    ),
    new Choice(
        id: 'department',
        options: ChoiceOptions::of(
            new ChoiceOption('billing', 'Charges, invoices, and payment problems.'),
            new ChoiceOption('technical', 'Bugs, errors, and integration problems.'),
            new ChoiceOption('sales', 'Plans, pricing, and account questions.'),
        ),
        instructions: 'Which department should handle this customer message?',
    ),
    new Score(
        id: 'frustration',
        levels: ScoreLevels::of(
            'Calm and neutral.',
            'Concerned but civil.',
            'Very angry or using strong language.',
        ),
        instructions: 'How frustrated does the customer appear?',
    ),
);

$answers = Decision::using('typesafe')
    ->with(
        input: 'I was charged twice for order A-104. Please refund the duplicate today.',
        questions: $questions,
    )
    ->get();

$refund = $answers->noul('refund_requested');
$department = $answers->choice('department');
$frustration = $answers->score('frustration');

echo 'Refund requested: '.number_format($refund->probability(), 2)."\n";
echo "Department: {$department->value()} (confidence "
    .number_format($department->confidence(), 2).")\n";
echo 'Frustration: '.number_format($frustration->value(), 2)
    .' (confidence '.number_format($frustration->confidence(), 2).")\n";

assert($refund->probability() >= 0.0 && $refund->probability() <= 1.0);
assert(in_array($department->value(), ['billing', 'technical', 'sales'], true));
assert($frustration->value() >= 0.0 && $frustration->value() <= 2.0);
?>
```
