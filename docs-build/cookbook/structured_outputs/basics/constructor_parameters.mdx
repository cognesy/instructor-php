---
title: 'Specifying required and optional parameters via constructor'
docname: 'constructor_parameters'
---

## Overview

Instructor can extract data from the LLM response and use it
to instantiate an object via constructor parameters.

Instructor will use the constructor parameters nullability
and default values to determine which parameters are required
and which are optional.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class UserWithConstructor
{
    public string $name;
    private ?int $age;
    private string $location;
    private string $password;

    public function __construct(
        string $name,                 // required - required in constructor, required internally
        int $age,                     // required - required in constructor, even if nullable internally
        ?string $location,            // optional - nullable in constructor, even if required internally
        string $password = '123admin' // optional - has a default value, even if required internally
    ) {
        $this->name = $name;
        $this->age = $age;
        $this->location = $location ?? '';
        $this->password = $password;
    }

    public function getAge(): int {
        return $this->age;
    }

    public function getLocation(): string {
        return $this->location;
    }

    public function getPassword(): string {
        return $this->password;
    }
}

$text = <<<TEXT
    Jason is 25 years old.
    TEXT;


$user = (new StructuredOutput)
    ->withMessages($text)
    ->withResponseClass(UserWithConstructor::class)
    ->get();

dump($user);

assert($user->name === "Jason");
assert($user->getAge() === 25);
assert($user->getPassword() === '123admin');
assert($user->getLocation() === ''); // default value for location
?>
```
