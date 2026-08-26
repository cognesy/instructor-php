---
title: 'Tell Harness: Typed Reasoning Configuration'
docname: 'tell_harness_reasoning_configuration'
order: 11
id: 'd1111'
tags:
  - 'tell'
  - 'tell-harness'
  - 'reasoning'
  - 'configuration'
---
## Overview

Select reasoning effort with a provider-independent enum. Branch configuration
can retain the intent for a line of work, while one request can override it.
Tell validates the effective provider and model before inference and translates
the typed value into provider-native Polyglot options only at the runtime edge.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Tell\Tell;
use Cognesy\Tell\TellReasoningEffort;
use Cognesy\Tell\TellRequest;

$project = TellHarnessExample::project();

try {
    $tell = Tell::testing($project, 'reasoned answer');
    $tell->workspace()->initialize();
    $configuration = $tell->workspace()->configuration();

    $connection = $configuration->set('connection', 'deepseek', 0);
    $model = $configuration->set(
        'model',
        'deepseek-v4-flash',
        $connection->version,
    );
    $configuration->set('reasoningEffort', 'medium', $model->version);

    $request = TellRequest::prompt('Answer with a short justification.')
        ->reasoningEffort(TellReasoningEffort::Low)
        ->durable();
    $effective = $configuration->effective($request);
    $result = $tell->run($request);

    echo 'Effort: '.$effective->values['reasoningEffort']."\n";
    echo 'Source: '.$effective->provenance['reasoningEffort']."\n";
    echo trim($result->text())."\n";

    assert($effective->values['reasoningEffort'] === 'low');
    assert($effective->provenance['reasoningEffort'] === 'invocation');
    assert(trim($result->text()) === 'reasoned answer');
} finally {
    TellHarnessExample::remove($project);
}
```

## Key Points

- Use `TellReasoningEffort` in PHP; branch storage uses its stable string value.
- Request intent wins over branch intent and effective configuration reports
  whether the value came from `invocation` or `branch`.
- Unsupported provider/model combinations fail before provider I/O.
- DeepSeek V4 and current Qwen 3 models advertise explicit reasoning-effort
  support through Polyglot capability metadata.
