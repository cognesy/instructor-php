---
title: 'Validation across multiple fields'
docname: 'validation_multifield'
---

## Overview

Sometimes property level validation is not enough - you may want to check values of multiple
properties and based on the combination of them decide to accept or reject the response.
Or the assertions provided by Symfony may not be enough for your use case.

In such case you can easily add custom validation code to your response model by:
- using `ValidationMixin`
- and defining validation logic in `validate()` method.

In this example LLM should be able to correct typo in the message (graduation year we provided
is `1010` instead of `2010`) and respond with correct graduation year.

## Example

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__.'../../src/');

use Cognesy\Instructor\Features\Validation\Traits\ValidationMixin;
use Cognesy\Instructor\Features\Validation\ValidationResult;
use Cognesy\Instructor\Instructor;

class UserDetails
{
    use ValidationMixin;

    public string $name;
    public int $birthYear;
    public int $graduationYear;

    public function validate() : ValidationResult {
        if ($this->graduationYear > $this->birthYear) {
            return ValidationResult::valid();
        }
        return ValidationResult::fieldError(
            field: 'graduationYear',
            value: $this->graduationYear,
            message: "Graduation year has to be bigger than birth year."
        );
    }
}

$user = (new Instructor)
    ->wiretap(fn($e) => $e->print())
    ->respond(
        messages: [['role' => 'user', 'content' => 'Jason was born in 2000 and graduated in 23.']],
        responseModel: UserDetails::class,
        model: 'gpt-3.5-turbo',
        maxRetries: 2,
    );


dump($user);

assert($user->graduationYear === 2023);
?>
```