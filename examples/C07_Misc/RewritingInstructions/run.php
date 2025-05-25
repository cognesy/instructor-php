---
title: 'Ask LLM to rewrite instructions'
docname: 'rewrite_instructions'
---

## Overview

Asking LLM to rewrite the instructions and rules is another way to improve
inference results.

You can provide arbitrary instructions on the data handling in the class
and property PHPDocs. Instructor will use these instructions to guide LLM
in the inference process.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;

/**
 * Identify what kind of job the user is doing.
 * Typical roles we're working with are CEO, CTO, CFO, CMO.
 * Sometimes user does not state their role directly - you will need
 * to make a guess, based on their description.
 */
class UserRole
{
    /**
     * Rewrite the instructions and rules in a concise form to correctly
     * determine the user's title - just the essence.
     */
    public string $instructions;
    /** Role description */
    public string $description;
    /** Most likely job title */
    public string $title;
}

class UserDetail
{
    public string $name;
    public int $age;
    public UserRole $role;
}

$text = <<<TEXT
    I'm Jason, I'm 28 yo. I am responsible for driving growth of our
    company.
    TEXT;

$structuredOutput = new StructuredOutput;
$user = $structuredOutput->with(
    messages: [["role" => "user",  "content" => $text]],
    responseModel: UserDetail::class,
)->get();

dump($user);

assert($user->name === "Jason");
assert($user->age === 28);
assert(!empty($user->role->title));
?>
```

