---
title: 'Extracting sequences of objects'
docname: 'sequences'
id: 'afb7'
---
## Overview

Sequences are a special type of response model that can be used to represent
a list of objects.

It is usually more convenient not create a dedicated class with a single array
property just to handle a list of objects of a given class.

Additional, unique feature of sequences is that they can be streamed per each
completed item in a sequence, rather than on any property update.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Sequence\Sequence;
use Cognesy\Instructor\StructuredOutput;

class Person
{
    public string $name;
    public int $age;
}

$text = <<<TEXT
    Jason is 25 years old. Jane is 18 yo. John is 30 years old. Anna is 2 years younger than him.
    TEXT;

print("INPUT:\n$text\n\n");

print("OUTPUT:\n");
$list = (new StructuredOutput)
    ->onSequenceUpdate(fn($sequence) => dump($sequence->last()))
    //->wiretap(fn($e) => $e->print())
    ->with(
        messages: $text,
        responseModel: Sequence::of(Person::class),
        options: ['stream' => true],
    )
    ->get();


dump(count($list));

assert(count($list) === 4);
?>
```
