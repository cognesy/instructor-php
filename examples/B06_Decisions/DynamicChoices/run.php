---
title: 'Dynamic decision choices'
docname: 'decision_dynamic_choices'
id: '118b'
tags:
  - 'no-replay'
  - 'decisions'
  - 'typesafe'
  - 'choice'
  - 'routing'
---
## Overview

Build Choice options from application data and keep automation policy in code.
The model selects only from the supplied route IDs and returns the complete
probability distribution.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Decision;
use Cognesy\Polyglot\Decision\Questions\Choice;

$availableRoutes = [
    ['id' => 'billing', 'description' => 'Charges, invoices, refunds, and payment failures.'],
    ['id' => 'technical', 'description' => 'Product bugs, API errors, and integration failures.'],
    ['id' => 'account', 'description' => 'Login, profile, permissions, and account access.'],
    ['id' => 'other', 'description' => 'None of the other routes fits.'],
];

$options = ChoiceOptions::of(...array_map(
    static fn (array $route): ChoiceOption => new ChoiceOption(
        id: $route['id'],
        description: $route['description'],
    ),
    $availableRoutes,
));

$answers = Decision::using('typesafe')
    ->with(
        input: 'CSV export now returns a 500 error for every project in our workspace.',
        questions: Questions::of(new Choice(
            id: 'route',
            options: $options,
            instructions: 'Which available support route best fits this message?',
        )),
    )
    ->get();

$route = $answers->choice('route');
$automaticRoute = $route->confidence() >= 0.60
    ? $route->value()
    : 'human-review';

echo "Selected route: {$route->value()}\n";
echo 'Probabilities: '.json_encode(
    $route->probabilities()->toArray(),
    JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR,
)."\n";
echo "Application action: {$automaticRoute}\n";

assert($options->has($route->value()));
assert(count($route->probabilities()->all()) === $options->count());
assert($automaticRoute === 'human-review' || $options->has($automaticRoute));
?>
```
