# Support for Anthropic API

Instructor supports Anthropic API - you can find the details on how to configure
the client in the example below.

Mode compatibility:
- Mode::MdJson, Mode::Json - supported
- Mode::Tools - not supported yet


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Anthropic\AnthropicClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

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

// Create instance of client initialized with custom parameters
$client = new AnthropicClient(
    apiKey: Env::get('ANTHROPIC_API_KEY'),
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'claude-3-haiku-20240307',
    mode: Mode::Tools,
    examples: [[
        'input' => 'Ive got email Frank - their developer. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
        'output' => ['age' => null, 'name' => 'Frank', 'role' => 'developer', 'hobbies' => ['playing drums'],],
    ]],
);

print("Completed response model:\n\n");

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
