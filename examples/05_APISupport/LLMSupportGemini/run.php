# Support for Google Gemini API

Google offers Gemini models which perform well in bechmarks.
Here's how you can use Instructor with Gemini API.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Gemini\GeminiClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

enum UserType : string {
    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
}

class User {
    public ?int $age;
    public string $name;
    public string $username;
    public UserType $role;
    /** @var string[] */
    public array $hobbies;
}

// Mistral instance params
$yourApiKey = Env::get('GEMINI_API_KEY'); // set your own API key

// Create instance of client initialized with custom parameters
$client = new GeminiClient(
    apiKey: $yourApiKey,
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor//->withDebug()
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        model: 'gemini-1.5-flash',
        options: ['stream' => true],
        examples: [[
            'input' => 'Ive got email Frank - their developer. He asked to come back to him frank@hk.ch. Btw, he plays on drums!',
            'output' => ['age' => null, 'name' => 'Frank', 'role' => 'developer', 'hobbies' => ['playing drums'],],
        ]],
        mode: Mode::MdJson,
    );

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
