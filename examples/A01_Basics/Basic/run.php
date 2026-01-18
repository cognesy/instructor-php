<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

// Step 1: Define a class that represents the structure and semantics
// of the data you want to extract
class User {
    public int $age;
    public string $name;
}

// Step 2: Get the text (or chat messages) you want to use as context
$text = "Jason is 25 years old and works as an engineer.";
print("Input text:\n");
print($text . "\n\n");

// Step 3: Extract structured data using default language model API (OpenAI)
print("Extracting structured data using LLM...\n\n");
$user = (new StructuredOutput)
    ->using('openai')
    ->withMessages($text)
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
