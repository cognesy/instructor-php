# Support for Mistral API

Mistral.ai is a company that builds OS language models, but also offers a platform
hosting those models. You can use Instructor with Mistral API by configuring the
client as demonstrated below.

Please note that the larger Mistral models support Mode::Json, which is much more
reliable than Mode::MdJson.

Mode compatibility:
 - Mode::Tools - Mistral-Small / Mistral-Medium / Mistral-Large
 - Mode::Json - Mistral-Small / Mistral-Medium / Mistral-Large
 - Mode::MdJson - Mistral 7B / Mixtral 8x7B


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Mistral\MistralClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

enum UserType : string {
    case Guest = 'guest';
    case User = 'user';
    case Admin = 'admin';
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
$yourApiKey = Env::get('MISTRAL_API_KEY'); // set your own API key

// Create instance of client initialized with custom parameters
$client = new MistralClient(
    apiKey: $yourApiKey,
    baseUri: 'https://api.mistral.ai/v1',
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        model: 'open-mixtral-8x7b',
        mode: Mode::Json,
    );

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
