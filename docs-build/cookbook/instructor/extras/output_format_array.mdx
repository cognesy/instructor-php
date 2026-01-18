<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

// Define schema as a class (sent to LLM for structure/validation)
class Person {
    public string $name;
    public int $age;
    public string $occupation;
}

// Extract data and receive as array instead of object
$personArray = (new StructuredOutput)
    ->withResponseClass(Person::class)  // Schema definition
    ->intoArray()                        // Return as array
    ->withMessages("Jason is 25 years old and works as a software engineer.")
    ->get();

dump($personArray);

// Result is a plain associative array
assert(is_array($personArray));
assert($personArray['name'] === 'Jason');
assert($personArray['age'] === 25);
assert($personArray['occupation'] === 'software engineer');

// No object instantiation occurred
assert(!is_object($personArray));

echo "\nExtracted data as array:\n";
echo "Name: {$personArray['name']}\n";
echo "Age: {$personArray['age']}\n";
echo "Occupation: {$personArray['occupation']}\n";
?>
