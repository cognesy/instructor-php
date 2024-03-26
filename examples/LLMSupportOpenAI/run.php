# Support for OpenAI API

This is the default client used by Instructor.

Mode compatibility:
 - Mode::Tools (recommended)
 - Mode::Json
 - Mode::MdJson


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
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

// OpenAI auth params
$yourApiKey = Env::get('OPENAI_API_KEY'); // use your own API key

// Create instance of OpenAI client initialized with custom parameters
$client = new OpenAIClient(
    apiKey: $yourApiKey,
    baseUri: 'https://api.openai.com/v1',
    organization: '',
    connectTimeout: 3,
    requestTimeout: 30,
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'gpt-3.5-turbo',
    //options: ['stream' => true ]
);

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
