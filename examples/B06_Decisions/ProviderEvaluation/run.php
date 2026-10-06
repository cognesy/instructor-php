---
title: 'Evaluate Decision providers on a fixed labeled corpus'
docname: 'decision_provider_evaluation'
id: 'd52e'
tags:
  - 'no-replay'
  - 'decisions'
  - 'evaluation'
  - 'typesafe'
  - 'classifier-dev'
  - 'jeff'
  - 'laya'
  - 'fastino'
---
## Overview

Run one explicit Decision provider against a small, versioned support-triage
corpus. The JSON report keeps native and projected semantics visible and reports
quality, calibration, latency, failures, retries, and an illustrative human
review policy separately.

The corpus demonstrates the mechanics. Replace it with representative,
independently labeled application data before making a production decision.

Execute the evaluator with a bundled preset name:

```bash
php examples/B06_Decisions/ProviderEvaluation/evaluate.php classifier-dev
```

Other bundled presets work the same way, for example `fastino` with
`FASTINO_API_KEY` set. A cold GLiDE model can make the first case slow or
retried; see the Fastino guide for warming-aware timeouts and retries.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Polyglot\Decision\Decision;

$response = Decision::using('classifier-dev')
    ->with(input: 'Refund the duplicate charge today.', questions: $questions)
    ->response();

// Evaluate typed probabilities and values against independently labeled cases.
$refundProbability = $response->answers()->noul('refund_requested')->probability();
$department = $response->answers()->choice('department')->value();
$urgency = $response->answers()->score('urgency')->value();
?>
```
