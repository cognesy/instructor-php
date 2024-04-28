# Basic use

Instructor allows you to use large language models to extract information
from the text (or content of chat messages), while following the structure
you define.

LLM does not 'parse' the text to find and retrieve the information.
Extraction leverages LLM ability to comprehend provided text and infer
the meaning of the information it contains to fill fields of the
response object with values that match the types and semantics of the
class fields.

The simplest way to use the Instructor is to call the `respond` method
on the Instructor instance. This method takes a string (or an array of
strings in the format of OpenAI chat messages) as input and returns a
data extracted from provided text (or chat) using the LLM inference.

Returned object will contain the values of fields extracted from the text.

The format of the extracted data is defined by the response model, which
in this case is a simple PHP class with some public properties.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Instructor;

// Step 1: Define a class that represents the structure and semantics
// of the data you want to extract
class User {
    public int $age;
    public string $name;
}

// Step 2: Get the text (or chat messages) you want to extract data from
$text = "Jason is 25 years old and works as an engineer.";
print("Input text:\n");
print($text . "\n\n");

// Step 3: Extract structured data using default language model API (OpenAI)
print("Extracting structured data using LLM...\n\n");
$user = (new Instructor)->respond(
    messages: $text,
    responseModel: User::class,
);

// Step 4: Now you can use the extracted data in your application
print("Extracted data:\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
