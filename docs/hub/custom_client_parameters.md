# Customize parameters of OpenAI client

You can provide your own OpenAI client instance to Instructor. This is useful
when you want to initialize OpenAI client with custom values - e.g. to call
other LLMs which support OpenAI API.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;

class User {
    public int $age;
    public string $name;
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

// Custom model and execution mode
$model = 'gpt-3.5-turbo';
$executionMode = Mode::Json;

$user = $instructor
    ->respond(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
    model: $model,
    mode: $executionMode,
);

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
