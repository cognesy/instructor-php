---
title: 'Return extracted data as array'
docname: 'output_format_array'
id: '4923'
---
## Overview

By default, Instructor deserializes extracted data into PHP objects. Sometimes you
may want to work with raw associative arrays instead - for example, when storing
data in a database, passing to a JSON API, or when you don't need the overhead
of object instantiation.

The `intoArray()` method allows you to use a PHP class to define the schema
(structure and validation sent to the LLM) while receiving the result as a plain
associative array.


## Example

```php
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
```

## Expected Output

```
array(3) {
  'name' =>
  string(5) "Jason"
  'age' =>
  int(25)
  'occupation' =>
  string(18) "software engineer"
}

Extracted data as array:
Name: Jason
Age: 25
Occupation: software engineer
```
