# Support for local Ollama

You can use Instructor with local Ollama instance. Please note that, at least currently,
OS models do not perform on par with OpenAI (GPT-3.5 or GPT-4) model.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;use Cognesy\Instructor\Events\Instructor\ResponseGenerated;use Cognesy\Instructor\Events\LLM\PartialJsonReceived;use Cognesy\Instructor\Instructor;use OpenAI\Client;

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

// Local Ollama instance params
$yourApiKey = 'ollama';
$yourBaseUri = 'http://localhost:11434/v1';
$model = 'llama2';
$executionMode = Mode::MdJson;

// Create instance of OpenAI client initialized with custom parameters
$client = OpenAI::factory()
    ->withApiKey($yourApiKey)
    ->withOrganization(null)
    ->withBaseUri($yourBaseUri)
    ->make();

/// Get Instructor with the default client component overridden with your own
$instructor = new Instructor([Client::class => $client]);

print("Printing partial updates:\n\n");

$user = $instructor
    ->onEvent(PartialJsonReceived::class, fn(PartialJsonReceived $event) => $event->print())
    ->onEvent(ResponseGenerated::class, fn(ResponseGenerated $event) => $event->print())
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        model: $model,
        mode: $executionMode,
        options: ['stream' => true]
    );

print("Completed response model:\n\n");
dump($user);

?>
```
