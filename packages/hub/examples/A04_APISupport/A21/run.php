---
title: 'A21'
docname: 'a21'
---

## Overview

Support for A21 Jamba - MAMBA architecture models, very strong at handling
long context.

Mode compatibility:
- OutputMode::Tools (supported)
- OutputMode::Json (supported)
- OutputMode::JsonSchema (supported)
- OutputMode::MdJson (fallback)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\LLM\Enums\OutputMode;

enum UserType : string {
    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
}

class User {
    public int $age;
    public string $name;
    public string $username;
    public UserType $role;
    /** @var string[] */
    public array $hobbies;
}

// Get Instructor with specified LLM client connection
// See: /config/llm.php to check or change LLM client connection configuration details
$structuredOutput = (new StructuredOutput)->withConnection('a21');

$user = $structuredOutput->create(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'jamba-1.5-mini', // set your own value/source
    mode: OutputMode::Json,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. His Twitter handle is @frankch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => '@frankch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
)->get();

print("Completed response model:\n\n");

dump($user);

assert(isset($user->name));
assert(isset($user->role));
assert(isset($user->age));
assert(isset($user->hobbies));
assert(isset($user->username));
assert(is_array($user->hobbies));
assert(count($user->hobbies) > 0);
assert($user->role === UserType::Admin);
assert($user->age === 25);
assert($user->name === 'Jason');
assert(in_array($user->username, ['jxnlco', '@jxnlco']));
?>
```
