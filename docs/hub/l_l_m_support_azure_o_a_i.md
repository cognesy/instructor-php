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
use OpenAI\Client;

class User {
    public int $age;
    public string $name;
}

/// Custom client parameter, e.g. API key and base URI
$apiKey = Env::get('AZURE_OPENAI_API_KEY'); // set your own value/source
$resourceName = Env::get('AZURE_OPENAI_RESOURCE_NAME'); // set your own value/source
$deploymentId = 'gpt-35-turbo-16k'; // set your own value/source
$apiVersion = '2024-02-01'; // set your own value/source
$baseUri = "{$resourceName}.openai.azure.com/openai/deployments/{$deploymentId}";

$client = OpenAI::factory()
    ->withBaseUri($baseUri)
    ->withHttpHeader('api-key', $apiKey)
    ->withQueryParam('api-version', $apiVersion)
    ->make();

/// Get Instructor with the default client component overridden with your own
$instructor = new Instructor([Client::class => $client]);

// Custom model and execution mode
$model = 'gpt-35-turbo-16k';
$executionMode = Mode::Tools;

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
