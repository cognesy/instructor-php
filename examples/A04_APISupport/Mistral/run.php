---
title: 'Mistral AI'
docname: 'mistralai'
---

## Overview

Mistral.ai is a company that builds OS language models, but also offers a platform
hosting those models. You can use Instructor with Mistral API by configuring the
client as demonstrated below.

Please note that the larger Mistral models support OutputMode::Json, which is much more
reliable than OutputMode::MdJson.

Mode compatibility:
 - OutputMode::Tools - supported (Mistral-Small / Mistral-Medium / Mistral-Large)
 - OutputMode::Json - recommended (Mistral-Small / Mistral-Medium / Mistral-Large)
 - OutputMode::MdJson - fallback mode (Mistral 7B / Mixtral 8x7B)

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
$structuredOutput = (new StructuredOutput)->using('mistral');

$user = $structuredOutput->create(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer, who\'s 30. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => 30, 'name' => 'Frank', 'username' => 'frank@hk.ch', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ],[
        'input' => 'We have a meeting with John, our new user. He is 30 years old - check his profile: @jx90.',
        'output' => ['name' => 'John', 'role' => 'admin', 'hobbies' => [], 'username' => 'jx90', 'age' => 30],
    ]],
    model: 'mistral-small-latest', //'open-mixtral-8x7b',
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
