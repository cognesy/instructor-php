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
