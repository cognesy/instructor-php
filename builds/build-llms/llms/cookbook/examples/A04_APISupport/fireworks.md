---
title: 'Fireworks.ai'
docname: 'fireworks'
id: '9592'
---
## Overview

Please note that the larger Mistral models support OutputMode::Json, which is much more
reliable than OutputMode::MdJson.

Mode compatibility:
- OutputMode::Tools - selected models
- OutputMode::Json - selected models
- OutputMode::MdJson


## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Instructor\Enums\OutputMode;
use Cognesy\Instructor\StructuredOutput;
use Cognesy\Instructor\StructuredOutputRuntime;
use Cognesy\Polyglot\Inference\LLMProvider;

enum UserType: string
{
    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
}

class User
{
    public int $age;

    public string $name;

    public string $username;

    public UserType $role;

    /** @var string[] */
    public array $hobbies;
}

$structuredOutput = new StructuredOutput(
    StructuredOutputRuntime::fromProvider(LLMProvider::using('fireworks'))
        ->withOutputMode(OutputMode::Json)
);

$user = $structuredOutput
    ->with(
        messages: 'Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.',
        responseModel: User::class,
        examples: [[
            'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
            'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums']],
        ]],
        model: 'accounts/fireworks/models/deepseek-v3p1',
    )->get();

echo "Completed response model:\n\n";
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
