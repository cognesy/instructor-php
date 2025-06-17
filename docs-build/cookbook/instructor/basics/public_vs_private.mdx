---
title: 'Private vs public object field'
docname: 'public_vs_private'
---

## Overview

Instructor only sets accessible fields of the object with the data provided by LLM.

Private and protected fields are left unchanged, unless:
 - class has constructor with parameters matching one or more property names - in such
   situation object will be hydrated with data from LLM via constructor params,
 - class has getXxx() and setXxx() methods with xxx matching one of the property names -
   in such situation object will be hydrated with data from LLM via setter methods

If you want to access them directly after extraction, provide default values for them.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

$text = <<<TEXT
    Jason is 25 years old. His password is '123admin'.
    TEXT;


// CASE 1: Class with public fields

class User
{
    public string $name;
    public int $age;
    public string $password = '';
}

$user = (new StructuredOutput)
    ->withMessages($text)
    ->withResponseClass(User::class)
    ->get();


echo "User with public fields\n";

dump($user);

assert($user->name === "Jason");
assert($user->age === 25);
assert($user->password === '123admin');


// CASE 2: Class with some private fields

class UserWithPrivateFields
{
    public string $name;
    private int $age = 0;
    private string $password = '';

    public function getAge() : int {
        return $this->age;
    }

    public function getPassword(): string {
        return $this->password;
    }
}

$userPriv = (new StructuredOutput)
    ->withMessages($text)
    ->withResponseClass(UserWithPrivateFields::class)
    ->get();

echo "Private 'password' and 'age' fields are not hydrated by Instructor\n";

dump($userPriv);

assert($userPriv->name === "Jason");
assert($userPriv->getAge() === 0);
assert($userPriv->getPassword() === '');
?>
```
