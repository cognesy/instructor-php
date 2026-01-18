<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

class User {
    public int $age;
    public string $name;
}

$text = "Jason is 25 years old and works as an engineer.";
print("Input text:\n");
print($text . "\n\n");

print("Extracting structured data using LLM...\n\n");
$user = (new StructuredOutput)
    ->using('openai')
    ->withMessages($text)
    ->withModel('gpt-3.5-turbo')
    ->withResponseClass(User::class)
    ->get();

// Step 4: Now you can use the extracted data in your application
print("Extracted data:\n");

dump($user);

assert(isset($user->name));
assert(isset($user->age));
assert($user->name === 'Jason');
assert($user->age === 25);
?>
