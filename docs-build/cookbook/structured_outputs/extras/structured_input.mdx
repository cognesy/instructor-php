---
title: 'Using structured data as an input'
docname: 'structured_input'
id: 'f7a0'
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
    ->withPrompt('Translate the subject and body fields to Spanish. Keep the address field unchanged.')
    ->withModel('gpt-4o-mini')
    ->get();

print_r($translatedEmail);

if ($translatedEmail->address !== $email->address) {
    echo "ERROR: Address was modified during translation\n";
    exit(1);
}
if ($translatedEmail->subject === $email->subject) {
    echo "ERROR: Subject was not translated\n";
    exit(1);
}
if ($translatedEmail->body === $email->body) {
    echo "ERROR: Body was not translated\n";
    exit(1);
}
?>
```
