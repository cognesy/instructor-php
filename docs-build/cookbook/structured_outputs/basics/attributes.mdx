---
title: 'Using attributes'
docname: 'attributes'
id: '4f7a'
---
## Overview

Instructor supports `Description` and `Instructions` attributes to provide more
context to the language model or to provide additional instructions to the model.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Schema\Attributes\Description;
use Cognesy\Schema\Attributes\Instructions;

// Step 1: Define a class that represents the structure and semantics
// of the data you want to extract
#[Description("Information about user")]
class User {
    #[Description("User's age")]
    public int $age;
    #[Instructions("Make user name ALL CAPS")]
    public string $name;
    #[Description("User's job")]
    #[Instructions("Ignore hobbies, identify profession")]
    #[Instructions("Make the profession name lowercase")]
    public string $job;
}

// Step 2: Get the text (or chat messages) you want to extract data from
$text = "Jason is 25 years old, 10K runner, speaker and an engineer.";
print("Input text:\n");
print($text . "\n\n");

// Step 3: Extract structured data using default language model API (OpenAI)
print("Extracting structured data using LLM...\n\n");
$user = (new StructuredOutput)->with(
    messages: $text,
    responseModel: User::class,
)->get();

// Step 4: Now you can use the extracted data in your application
print("Extracted data:\n");

dump($user);

assert(isset($user->name));
assert($user->name === "JASON");
assert(isset($user->age));
assert($user->age === 25);
assert(isset($user->job));
assert($user->job === "engineer");
?>
```
