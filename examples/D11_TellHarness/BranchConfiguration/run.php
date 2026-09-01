---
title: 'Tell Harness: Configure a Branch Without Storing Secrets'
docname: 'tell_harness_branch_configuration'
order: 7
id: 'd1107'
tags:
  - 'tell'
  - 'tell-harness'
  - 'configuration'
  - 'branches'
---
## Overview

Branch configuration records secret-free runtime intent: a connection label,
model, tool allow-list, and bounded execution policy. It never stores API
keys, DSNs, headers, or environment values. Writes use the version returned by
the previous read, so concurrent callers must re-read rather than overwrite.

## Example

```php
<?php
require 'examples/boot.php';
require_once dirname(__DIR__).'/Support.php';

use Cognesy\Tell\Composition\Standalone\Profile\StandaloneTellHost;

$project = TellHarnessExample::project();

try {
    $tell = StandaloneTellHost::open($project);
    $workspace = $tell->workspace();
    $workspace->initialize();
    $workspace->branches()->create('review', empty: true);

    $config = $workspace->configuration('review');
    $initial = $config->show();
    $model = $config->set('model', 'deepseek-v4-flash', $initial->version);
    $policy = $config->set('timeoutMs', 15_000, $model->version);
    $effective = $config->effective();

    echo 'Configured branch: '.$effective->branch."\n";
    echo 'Model source: '.$effective->provenance['model']."\n";
    echo 'Timeout: '.$effective->values['timeoutMs']."ms\n";

    // Delete must use the most recently observed version as well.
    $config->delete('timeoutMs', $policy->version);

    assert($effective->values['model'] === 'deepseek-v4-flash');
    assert($effective->provenance['timeoutMs'] === 'branch');
} finally {
    TellHarnessExample::remove($project);
}
?>
```

## Key Points

- `show()` returns only explicitly configured branch values; `effective()`
  resolves policy precedence and reports the source of every field.
- `set()` and `delete()` require an expected version. Re-read and retry when a
  concurrent writer has changed the record.
- Connection values are labels resolved against Tell presets. Keep credentials
  in the environment, workspace `.env`, or Tell credential store.
