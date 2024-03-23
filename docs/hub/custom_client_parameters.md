# Customize parameters of OpenAI client

You can provide your own OpenAI client instance to Instructor. This is useful
when you want to initialize OpenAI client with custom values - e.g. to call
other LLMs which support OpenAI API.

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;use Cognesy\Instructor\Instructor;use Cognesy\Instructor\Utils\Env;use OpenAI\Client;

class User {
    public int $age;
    public string $name;
}

/// Custom client parameter, e.g. API key and base URI
$yourApiKey = Env::get('OPENAI_API_KEY'); // or your own value/source
$yourBaseUri = 'https://api.openai.com/v1';

// Create instance of OpenAI client initialized with custom parameters
$client = OpenAI::factory()
    ->withApiKey($yourApiKey)
    ->withOrganization(null) // default: null
    ->withBaseUri($yourBaseUri) // default: api.openai.com/v1
    ->make();

/// Get Instructor with the default client component overridden with your own
$instructor = new Instructor([Client::class => $client]);

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
