# Support for local Ollama

You can use Instructor with local Ollama instance. Please note that, at least currently,
OS models do not perform on par with OpenAI (GPT-3.5 or GPT-4) model.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;

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

// Create instance of OpenAI client initialized with custom parameters for Ollama
$client = new OpenAIClient(
    apiKey: 'ollama',
    baseUri: 'http://localhost:11434/v1',
    connectTimeout: 3,
    requestTimeout: 60, // set based on your machine performance :)
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'llama2',
    mode: Mode::MdJson
    //options: ['stream' => true ]
);

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
