---
title: 'Automatic correction based on validation results'
docname: 'self_correction'
---

## Overview

Instructor uses validation errors to inform LLM on the problems identified
in the response, so that LLM can try self-correcting in the next attempt.

In case maxRetries parameter is provided and LLM response does not meet
validation criteria, Instructor will make subsequent inference attempts
until results meet the requirements or maxRetries is reached.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Instructor\Events\Response\ResponseValidated;
use Cognesy\Instructor\Events\Response\ResponseValidationAttempt;
use Cognesy\Instructor\Events\Response\ResponseValidationFailed;
use Cognesy\Instructor\StructuredOutput;
use Symfony\Component\Validator\Constraints as Assert;

class UserDetails
{
    public string $name;
    #[Assert\Email]
    public string $email;
}
$text = "you can reply to me via jason wp.pl -- Jason";

print("INPUT:\n$text\n\n");

print("RESULTS:\n");
$user = (new StructuredOutput)
    ->onEvent(HttpRequestSent::class, fn($event) => print("[ ] Requesting LLM response...\n"))
    ->onEvent(ResponseValidationAttempt::class, fn($event) => print("[?] Validating:\n    ".json_encode($event->response)."\n"))
    ->onEvent(ResponseValidationFailed::class, fn($event) => print("[!] Validation failed:\n    $event\n"))
    ->onEvent(ResponseValidated::class, fn($event) => print("[ ] Validation succeeded.\n"))
    ->with(
        messages: $text,
        responseModel: UserDetails::class,
        maxRetries: 3,
    )->get();

print("\nOUTPUT:\n");

dump($user);

assert($user->email === "jason@wp.pl");
?>
```
