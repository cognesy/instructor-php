---
title: 'Single label classification'
docname: 'classification'
---

## Overview

For single-label classification, we first define an `enum` for possible labels
and a PHP class for the output.

## Example

Let's start by defining the data structures.

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

// Enumeration for single-label text classification.
enum Label : string {
    case SPAM = "spam";
    case NOT_SPAM = "not_spam";
}

// Class for a single class label prediction.
class SinglePrediction {
    public Label $classLabel;
}
?>
```
## Classifying Text

The function classify will perform the single-label classification.

```php
<?php
// Perform single-label classification on the input text.
function classify(string $data) : SinglePrediction {
    return (new StructuredOutput)->with(
        messages: [[
            "role" => "user",
            "content" => "Classify the following text: $data",
        ]],
        responseModel: SinglePrediction::class,
    )->get();
}
?>
```

## Testing and Evaluation

Let's run an example to see if it correctly identifies a spam message.

```php
<?php
// Test single-label classification
$prediction = classify("Hello there I'm a Nigerian prince and I want to give you money");

dump($prediction);

assert($prediction->classLabel == Label::SPAM);
?>
```
