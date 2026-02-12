---
title: 'Handling errors with `Maybe` helper class'
docname: 'maybe'
id: '122b'
---
## Overview

You can create a wrapper class to hold either the result of an operation or an error message.
This allows you to remain within a function call even if an error occurs, facilitating
better error handling without breaking the code flow.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Extras\Maybe\Maybe;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class User
{
    public string $name;
    public int $age;
}


$text = 'We have no information about our new developer.';
echo "\nINPUT:\n$text\n";

$maybeUser = (new StructuredOutput)->with(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Maybe::is(User::class),
    model: 'gpt-4o-mini',
    mode: OutputMode::MdJson,
)->get();

echo "\nOUTPUT:\n";

dump($maybeUser->get());

assert($maybeUser->hasValue() === false);
assert(!empty($maybeUser->error()));
assert($maybeUser->get() === null);

$text = "Jason is our new developer, he is 25 years old.";
echo "\nINPUT:\n$text\n";

$maybeUser = (new StructuredOutput)->with(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Maybe::is(User::class)
)->get();

echo "\nOUTPUT:\n";

dump($maybeUser->get());

assert($maybeUser->hasValue() === true);
assert(empty($maybeUser->error()));
assert($maybeUser->get() != null);
assert($maybeUser->get() instanceof User);
?>
```
