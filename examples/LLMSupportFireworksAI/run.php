# Support for Fireworks.ai API


Please note that the larger Mistral models support Mode::Json, which is much more
reliable than Mode::MdJson.

Mode compatibility:
- Mode::Tools - selected models
- Mode::Json - selected models
- Mode::MdJson


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\FireworksAI\FireworksAIClient;
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

// Mistral instance params
$yourApiKey = Env::get('FIREWORKSAI_API_KEY'); // set your own API key

// Create instance of client initialized with custom parameters
$client = new FireworksAIClient(
    apiKey: $yourApiKey,
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        model: 'accounts/fireworks/models/mixtral-8x7b-instruct',
        mode: Mode::Json,
    //options: ['stream' => true ]
    );

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
