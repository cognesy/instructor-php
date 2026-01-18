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

// Get Instructor with specified LLM client connection
// See: /config/llm.php to check or change LLM client connection configuration details
$structuredOutput = (new StructuredOutput)->using('ollama');

$user = $structuredOutput->with(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    examples: [[
        'input' => 'Ive got email Frank - their developer. Asked to connect via Twitter @frankch. Btw, he plays on drums!',
        'output' => ['name' => 'Frank', 'role' => 'developer', 'hobbies' => ['playing drums'], 'username' => 'frankch', 'age' => null],
    ],[
        'input' => 'We have a meeting with John, our new user. He is 30 years old - check his profile: @j90.',
        'output' => ['name' => 'John', 'role' => 'admin', 'hobbies' => [], 'username' => 'j90', 'age' => 30],
    ]],
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
