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
use OpenAI\Client;

class User {
    public int $age;
    public string $name;
}

/// modify API key and base URI...
$yourApiKey = getenv('OPENAI_API_KEY');
$yourBaseUri = 'https://api.openai.com/v1';
$model = 'gpt-3.5-turbo';
$executionMode = Mode::Json;

// create instance of OpenAI client initialized with custom parameters
$client = OpenAI::factory()
    ->withApiKey($yourApiKey)
    ->withOrganization(null) // default: null
    ->withBaseUri($yourBaseUri) // default: api.openai.com/v1
    // you can provide other parameters as well...
    //->withHttpClient($client = new \GuzzleHttp\Client([]))
    //->withHttpHeader('X-My-Header', 'foo')
    //->withQueryParam('my-param', 'bar')
    //->withStreamHandler(fn (RequestInterface $request): ResponseInterface => $client->send($request, [
    //    'stream' => true // Allows to provide a custom stream handler for the http client.
    //]))
    ->make();

/// now you can get Instructor with the default client component overridden with your own
$instructor = new Instructor([Client::class => $client]);

$user = $instructor
    ->respond(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
    model: $model,
    mode: $executionMode,
);

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
