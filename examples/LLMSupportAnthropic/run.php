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
    case Guest = 'Guest';
    case User = 'User';
    case Admin = 'Admin';
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
    //options: ['stream' => true ]
);

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
