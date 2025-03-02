---
title: 'Limiting the length of lists'
docname: 'limiting_lists'
---

## Overview

When dealing with lists of attributes, especially arbitrary properties, it's crucial to manage
the length of list. You can use prompting and enumeration to limit the list length, ensuring
a manageable set of properties.

> To be 100% certain the list does not exceed the limit, add extra
> validation, e.g. using ValidationMixin (see: Validation).

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Features\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Features\Validation\ValidationResult;
use Cognesy\Instructor\Instructor;

class Property
{
    /**  Monotonically increasing ID, not larger than 2 */
    public string $index;
    public string $key;
    public string $value;
}

class UserDetail
{
    use ValidationMixin;

    public int $age;
    public string $name;
    /** @var Property[] List other extracted properties - not more than 2. */
    public array $properties;

    public function validate() : ValidationResult
    {
        if (count($this->properties) < 3) {
            return ValidationResult::valid();
        }
        return ValidationResult::fieldError(
            field: 'properties',
            value: $this->name,
            message: "Number of properties must be not more than 2.",
        );
    }
}

$text = <<<TEXT
    Jason is 25 years old. He is a programmer. He has a car. He lives in
    a small house in Alamo. He likes to play guitar.
    TEXT;

$user = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: UserDetail::class,
    maxRetries: 1 // change to 0 to see validation error
);

dump($user);

assert($user->age === 25);
assert($user->name === "Jason");
assert(count($user->properties) < 3);
?>
```