---
title: 'Groq'
docname: 'groq'
---

## Overview

Groq is LLM providers offering a very fast inference thanks to their
custom hardware. They provide a several models - Llama2, Mixtral and Gemma.

Supported modes depend on the specific model, but generally include:
 - OutputMode::MdJson - fallback mode
 - OutputMode::Json - recommended
 - OutputMode::Tools - supported

Here's how you can use Instructor with Groq API.

## Example

```php
\<\?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

enum UserType : string {
    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
}

class User {
    public string $name;
    public UserType $role;
    /** @var string[] */
    public array $hobbies;
    public string $username;
    public ?int $age;
}

// Get Instructor with specified LLM client connection
// See: /config/llm.php to check or change LLM client connection configuration details
$structuredOutput = (new StructuredOutput)->using('groq');

$user = $structuredOutput
    ->with(
        messages: "Jason (@jxnlco) is 25 years old. He is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        prompt: 'Parse the user data to JSON, respond using following JSON Schema: <|json_schema|>',
        examples: [[
            'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
            'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'user', 'hobbies' => ['playing drums'],],
        ],[
            'input' => 'We have a meeting with John, our new admin who likes surfing. He is 19 years old - check his profile: @jx90.',
            'output' => ['name' => 'John', 'role' => 'admin', 'hobbies' => ['surfing'], 'username' => 'jx90', 'age' => 19],
        ]],
        model: 'llama-3.3-70b-versatile', //'gemma2-9b-it',
        maxRetries: 2,
        options: ['temperature' => 0.5],
        mode: OutputMode::Json,
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
