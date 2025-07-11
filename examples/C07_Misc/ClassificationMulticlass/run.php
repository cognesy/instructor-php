---
title: 'Multiclass classification'
docname: 'classification_multiclass'
---

## Overview

We start by defining the structures.

For multi-label classification, we introduce a new enum class and a different PHP class
to handle multiple labels.

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

/** Potential ticket labels */
enum Label : string {
    case TECH_ISSUE = "tech_issue";
    case BILLING = "billing";
    case SALES = "sales";
    case SPAM = "spam";
    case OTHER = "other";
}

/** Represents analysed ticket data */
class TicketLabels {
    /** @var Label[] */
    public array $labels = [];
}
?>
```

## Classifying Text0

The function `multi_classify` executes multi-label classification using LLM.

```php
<?php
// Perform single-label classification on the input text.
function multi_classify(string $data) : TicketLabels {
    $x = (new StructuredOutput)
        //->withDebugPreset('on')
        ->wiretap(fn($e) => $e->printDebug())
        ->withMessages("Label following support ticket: {$data}")
        ->withResponseModel(TicketLabels::class)
        ->create();
dd($x);
//        ->get();
}
?>
```

## Testing and Evaluation

Finally, we test the multi-label classification function using a sample support ticket.

```php
<?php
// Test single-label classification
$ticket = "My account is locked and I can't access my billing info.";
$prediction = multi_classify($ticket);

dump($prediction);

assert(in_array(Label::TECH_ISSUE, $prediction->labels));
assert(in_array(Label::BILLING, $prediction->labels));
?>
```
