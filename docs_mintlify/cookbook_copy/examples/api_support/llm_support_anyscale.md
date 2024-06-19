# Support for Anyscale API

Anyscale is hosted language model provider that offers inference API with support for
chat completion, JSON completion, and tools call. You can use Instructor with Anyscale
as demonstrated below.

Please note that some models support Mode::Tools or Mode::Json, which are much
more reliable than Mode::MdJson.

Mode compatibility:
- Mode::Tools - selected models
- Mode::Json - selected models
- Mode::MdJson


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Anyscale\AnyscaleClient;
use Cognesy\Instructor\Enums\Mode;
use Cognesy\Instructor\Events\PartialsGenerator\StreamedResponseReceived;
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
$yourApiKey = Env::get('ANYSCALE_API_KEY'); // set your own API key

// Create instance of client initialized with custom parameters
$client = new AnyscaleClient(
    apiKey: $yourApiKey,
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor
    ->respond(
        messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
        responseModel: User::class,
        model: 'mistralai/Mixtral-8x7B-Instruct-v0.1',
        mode: Mode::Json,
        //options: ['stream' => true],
    );

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
