---
title: 'Restating instructions'
docname: 'restate_instructions'
---

## Overview

Make Instructor restate long or complex instructions and rules to improve inference
accuracy.

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
    /** Restate instructions and rules, so you can correctly determine the title. */
    public string $instructions;
    /** Role description */
    public string $description;
    /* Guess job title */
    public string $title;
}

/**
 * Details of analyzed user. The key information we're looking for
 * is appropriate role data.
 */
class UserDetail
{
    public string $name;
    public int $age;
    public UserRole $role;
}

$text = <<<TEXT
    I'm Jason, I'm 28 yo. I am the head of Apex Software, responsible for
    driving growth of our company.
    TEXT;

$structuredOutput = new StructuredOutput;
$user = ($structuredOutput)->create(
    messages: [["role" => "user",  "content" => $text]],
    responseModel: UserDetail::class,
)->get();

dump($user);

assert($user->name === "Jason");
assert($user->age === 28);
//assert(!empty($user->role->title));
?>
```
