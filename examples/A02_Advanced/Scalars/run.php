---
title: 'Extracting scalar values'
docname: 'scalars'
id: '318e'
tags:
  - 'advanced'
  - 'scalar-output'
  - 'extraction'
---
## Overview

Sometimes we just want to get quick results without defining a class for
the response model, especially if we're trying to get a straight, simple
answer in a form of string, integer, boolean or float. Instructor provides
a simplified API for such cases.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Scalar\Scalar;
use Cognesy\Instructor\StructuredOutput;

enum CitizenshipGroup : string {
    case US = "US";
    case Canada = "Canada";
    case Germany = "Germany";
    case Other = "Other";
}

$text = "His name is Jason, he is 28 years old American who lives in Germany.";
$question = "What is user's citizenship?";

echo "\nSOURCE TEXT:\n  {$text}\n";
echo "\nQUESTION:\n  {$question}\n";
echo "\nTARGET SCALAR:\n  CitizenshipGroup enum\n";

$value = StructuredOutput::using('openai')->with(
    messages: $text,
    prompt: $question,
    responseModel: Scalar::enum(CitizenshipGroup::class, name: 'citizenshipGroup'),
)->get();

echo "\nRETURNED SCALAR:\n  CitizenshipGroup::{$value->name} ({$value->value})\n";

assert($value instanceof CitizenshipGroup);
assert($value == CitizenshipGroup::US);
?>
```
