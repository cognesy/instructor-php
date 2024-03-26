# Support for OpenRouter API

You can use Instructor with OpenRouter API. OpenRouter provides easy, unified access
to multiple open source and commercial models. Read OpenRouter docs to learn more about
the models they support.

Please note that OS models are in general weaker than OpenAI ones, which may result in
lower quality of responses or extraction errors. You can mitigate this (partially) by using
validation and `maxRetries` option to make Instructor automatically reattempt the extraction
in case of extraction issues.


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenRouter\OpenRouterClient;
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

// OpenRouter client params
$yourApiKey = Env::get('OPENROUTER_API_KEY'); // or your own value/source

// Create instance of OpenAI client initialized with custom parameters
$client = new OpenRouterClient(
    apiKey: $yourApiKey,
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'mistralai/mixtral-8x7b-instruct:nitro',
    mode: Mode::Json,
    //options: ['stream' => true ]
);

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
