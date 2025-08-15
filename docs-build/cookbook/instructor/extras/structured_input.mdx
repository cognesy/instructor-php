---
title: 'Using structured data as an input'
docname: 'structured_input'
---

## Overview

Instructor offers a way to use structured data as an input. This is
useful when you want to use object data as input and get another object
with a result of LLM inference.

The `input` field of Instructor's `create()` method
can be an object, but also an array or just a string.


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class Email {
    public function __construct(
        public string $address = '',
        public string $subject = '',
        public string $body = '',
    ) {}
}

$email = new Email(
    address: 'joe@gmail',
    subject: 'Status update',
    body: 'Your account has been updated.'
);

$translatedEmail = (new StructuredOutput)
    ->withInput($email)
    ->withResponseClass(Email::class)
    ->withPrompt('Translate the text fields of email to Spanish. Keep other fields unchanged.')
    ->get();

dump($translatedEmail);

assert($translatedEmail->address === $email->address);
assert($translatedEmail->subject !== $email->subject);
assert($translatedEmail->body !== $email->body);
?>
```
