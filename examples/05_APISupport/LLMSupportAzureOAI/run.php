# Support for Azure OpenAI API

You can connect to Azure OpenAI instance using a dedicated client provided
by Instructor. Please note it requires setting up your own model deployment
using Azure OpenAI service console.


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\Azure\AzureClient;
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

/// Custom client parameters: base URI
$resourceName = Env::get('AZURE_OPENAI_RESOURCE_NAME'); // set your own value/source

$client = (new AzureClient(
    apiKey: Env::get('AZURE_OPENAI_API_KEY'),
    resourceName: 'instructor-dev', // set your own value/source
    deploymentId: 'gpt-35-turbo-16k', // set your own value/source
    apiVersion: '2024-02-01',
));

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

// Call with your model name and preferred execution mode
$user = $instructor->respond(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
    model: 'gpt-35-turbo-16k', // set your own value/source
    //options: ['stream' => true ]
);

print("Completed response model:\n\n");
dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
