# Using structured data as an input

Instructor offers a way to use structured data as an input. This is
useful when you want to use object data as input and get another object
with a result of LLM inference.

The `input` field of Instructor's `respond()` and `request()` methods
can be an object, but also an array or just a string.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

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

$translatedEmail = (new Instructor)->respond(
    input: $email,
    responseModel: Email::class,
    prompt: 'Translate the text fields of email to Spanish. Keep other fields unchanged.',
);

dump($translatedEmail);

assert($translatedEmail->address === $email->address);
assert($translatedEmail->subject !== $email->subject);
assert($translatedEmail->body !== $email->body);
?>
```
