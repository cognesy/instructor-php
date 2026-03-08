---
title: 'Minimaxi'
docname: 'minimaxi'
id: '1d6f'
---
## Overview

Support for Minimaxi's API.

Mode compatibility:
- OutputMode::MdJson (supported)
- OutputMode::Tools (not supported)
- OutputMode::Json (not supported)
- OutputMode::JsonSchema (not supported)

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Polyglot\Inference\Config\LLMConfig;

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
// Adjust provider credentials and model in the LLMConfig::fromArray([...]) values below.
$debugHttpClient = (new HttpClientBuilder)->withDebugConfig(ExampleConfig::debugPreset('on'))->create();
$structuredOutput = new StructuredOutput(
    StructuredOutputRuntime::fromConfig(
        config: ExampleConfig::llmPreset('minimaxi'),
        httpClient: $debugHttpClient,
    )
);

$user = $structuredOutput
    ->with(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        examples: [[
            'input' => 'Ive got email Frank - their developer, who\'s 30. His Twitter handle is @frankch. Btw, he plays on drums!',
            'output' => ['age' => 30, 'name' => 'Frank', 'username' => '@frankch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
        ]],
        model: 'MiniMax-Text-01', // set your own value/source
        mode: OutputMode::MdJson,
    )
    ->get();

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
