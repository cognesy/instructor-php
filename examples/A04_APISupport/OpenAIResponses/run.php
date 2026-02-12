---
title: 'OpenAI Responses API'
docname: 'openai-responses'
id: 'aada'
---
## Overview

OpenAI's Responses API is their new recommended API for inference, offering improved
performance and features compared to Chat Completions.

Key features:
- 3% better performance on reasoning tasks
- 40-80% improved cache utilization
- Built-in tools: web search, file search, code interpreter
- Server-side conversation state via `previous_response_id`
- Semantic streaming events

Mode compatibility:
 - OutputMode::Tools (supported)
 - OutputMode::Json (supported)
 - OutputMode::JsonSchema (recommended)
 - OutputMode::MdJson (fallback)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Polyglot\Inference\Enums\OutputMode;

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

// Get Instructor with OpenAI Responses API connection
// See: /config/llm.php to check or change LLM client connection configuration details
$structuredOutput = (new StructuredOutput)->using('openai-responses');

$user = $structuredOutput->with(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. His Twitter handle is @frankch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => '@frankch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
    model: 'gpt-4o-mini', // set your own value/source
    mode: OutputMode::JsonSchema,
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
