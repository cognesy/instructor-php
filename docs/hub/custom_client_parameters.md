# Customize parameters of OpenAI client

You can provide your own OpenAI client instance to Instructor. This is useful
when you want to initialize OpenAI client with custom values - e.g. to call
other LLMs which support OpenAI API.


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

class User {
    public int $age;
    public string $name;
}

// Create instance of OpenAI client initialized with custom parameters
$client = new OpenAIClient(
    apiKey: Env::get('OPENAI_API_KEY'),
    baseUri: 'https://api.openai.com/v1',
    connectTimeout: 3,
    requestTimeout: 30,
    metadata: ['organization' => ''],
);

// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)
    ->withClient($client);

// Call with custom model and execution mode
$user = $instructor->respond(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
    model: 'gpt-3.5-turbo',
    mode: Mode::Json,
);

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
