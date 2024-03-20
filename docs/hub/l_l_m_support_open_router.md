# Support for OpenRouter API

You can use Instructor with OpenRouter API. OpenRouter provides easy, unified access
to multiple open source and commercial models. Read OpenRouter docs to learn more about
the models they support.

OpenRouter API has a minor inconsistency with original OpenAI specification in the response
format, which prevents standard OpenAI PHP client to work with it correctly in non-streaming
mode. We have created a custom adapter for OpenRouter that fixes this issue.

Please note that OS models are in general weaker than OpenAI ones, which may result in
lower quality of responses or extraction errors. You can mitigate this (parially) by using
validation and `maxRetries` option to make Instructor automatically reattempt the extraction
in case of extraction issues.


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\RequestHandler\NewValidationRecoveryAttempt;
use Cognesy\Instructor\Events\RequestHandler\ValidationRecoveryLimitReached;
use Cognesy\Instructor\Events\ResponseHandler\ResponseValidationAttempt;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\LLMs\OpenRouter\OpenRouterClient;
use Cognesy\Instructor\Utils\Env;
use OpenAI\Client;

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
$yourApiKey = Env::get('OPENROUTER_API_KEY'); // or your own value/source
$yourBaseUri = 'https://openrouter.ai/api/v1';
$model = 'mistralai/mistral-7b-instruct:free';

$executionMode = Mode::MdJson;

// Create instance of OpenAI client initialized with custom parameters
$client = OpenAI::factory()
    ->withApiKey($yourApiKey)
    ->withBaseUri($yourBaseUri)
    ->withHttpClient(OpenRouterClient::getClient()) // required!
    ->make();

/// Get Instructor with the default client component overridden with your own
$instructor = new Instructor([Client::class => $client]);

print("Printing partial updates:\n\n");

$user = $instructor
    //->onEvent(PartialJsonReceived::class, fn(PartialJsonReceived $event) => $event->print())
    ->onEvent(ResponseValidationAttempt::class, fn($event) => $event->print())
    ->onEvent(NewValidationRecoveryAttempt::class, fn($event) => $event->print())
    ->onEvent(ValidationRecoveryLimitReached::class, fn($event) => $event->print())
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        model: $model,
        mode: $executionMode,
        options: ['stream' => true],
        maxRetries: 3,
    );

print("Completed response model:\n\n");
dump($user);

?>
```
