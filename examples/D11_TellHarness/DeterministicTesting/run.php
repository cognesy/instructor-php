---
title: 'Tell Harness: Test Deterministically Without Provider I/O'
docname: 'tell_harness_deterministic_testing'
order: 10
id: 'd1110'
tags:
  - 'tell'
  - 'tell-harness'
  - 'testing'
---
## Overview

Exercise Tell's real SDK/runtime surface with scripted model steps. The testing
factory keeps request compilation, policies, events, tools, and persistence
real while replacing provider inference with an in-process deterministic driver.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Agents\Drivers\Testing\ScenarioStep;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Tell\Tell;
use Cognesy\Tell\TellRequest;
use Cognesy\Tell\Testing\TellTestFactory;

$project = TellHarnessExample::project();

try {
    $result = Tell::testing($project, 'deterministic answer')->run(
        TellRequest::prompt('This prompt never leaves the PHP process.'),
    );

    $failure = TellTestFactory::steps(
        ScenarioStep::error('expected failure'),
    )->open($project)->run(
        TellRequest::prompt('Exercise a controlled terminal failure.'),
    );

    echo $result->text()."\n";
    echo 'Failure status: '.$failure->status()?->value."\n";

    assert($result->isCompleted());
    assert(trim($result->text()) === 'deterministic answer');
    assert($failure->status() === ExecutionStatus::Failed);
} finally {
    TellHarnessExample::remove($project);
}
```

## Key Points

- `Tell::testing()` is the short path for one or more deterministic final
  responses.
- `TellTestFactory::steps()` scripts tool, final, usage, and failure states.
- No HTTP call or real provider credential is required. Test state stays under
  the supplied project at `.tell-testing`, so temporary-project cleanup owns
  the complete lifecycle.
