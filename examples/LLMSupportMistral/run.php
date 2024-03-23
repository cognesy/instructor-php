# Support for Mistral API

Mistral.ai is a company that builds OS language models, but also offers a platform
hosting those models. You can use Instructor with Mistral API by configuring the
client parameters as demonstrated below.

Please note that the larger Mistral models support Mode::Json and Mode::Tools, which
are much more reliable than Mode::MdJson.

Mode compatibility:
 - Mode::Tools, Mode::Json - Mistral-Small / Mistral-Medium / Mistral-Large
 - Mode::MdJson - Mistral 7B / Mixtral 8x7B


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\LLM\ResponseReceivedFromLLM;
use Cognesy\Instructor\Instructor;
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
$yourApiKey = Env::get('MISTRAL_API_KEY');
$yourBaseUri = 'https://api.mistral.ai/v1';

// Create instance of OpenAI client initialized with custom parameters
$client = OpenAI::factory()
    ->withApiKey($yourApiKey)
    ->withOrganization(null)
    ->withBaseUri($yourBaseUri)
    ->make();

/// Get Instructor with the default client component overridden with your own
$instructor = new Instructor([Client::class => $client]);

print("Printing partial updates:\n\n");

//$models = ['open-mistral-7b', 'open-mixtral-8x7b', 'mistral-small-latest	', 'mistral-medium-latest', 'mistral-large-latest'];
$model = 'mistral-large-latest';
$executionMode = Mode::Tools;

$user = $instructor
    ->onEvent(ResponseReceivedFromLLM::class, fn($event) => dump($event))
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        model: $model,
        mode: $executionMode,
        options: ['stream' => true ]
    );

print("Completed response model:\n\n");
dump($user);

?>
```
