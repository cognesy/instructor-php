---
title: 'Arbitrary properties'
docname: 'arbitrary_properties'
---

## Overview

When you need to extract undefined attributes, use a list of key-value pairs.


## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

class Property
{
    public string $key;
    public string $value;
}

class UserDetail
{
    public int $age;
    public string $name;
    /** @var Property[] Extract any other properties that might be relevant */
    public array $properties;
}
?>
```

Now we can use this data model to extract arbitrary properties from a text message
in a form that is easier for future processing.

```php
\<\?php
$text = <<<TEXT
    Jason is 25 years old. He is a programmer. He has a car. He lives
    in a small house in Alamo. He likes to play guitar.
    TEXT;

$user = (new StructuredOutput)->with(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetail::class,
    mode: OutputMode::Json,
)->get();

dump($user);

assert($user->age === 25);
assert($user->name === "Jason");
assert(!empty($user->properties));
?>
```
