# Debugging

Instructor gives you access to SaloonPHP debugging mode via API client class
`withDebug()` method. Calling it on a client instance causes underlying SaloonPHP
library to output HTTP request and response details to the console, so you can
see what is being sent to LLM API and what is being received.

You can also directly access Saloon connector instance via `connector()` method
on the client instance, and call Saloon debugging methods on it - see SaloonPHP
debugging documentation for more details:
https://docs.saloon.dev/the-basics/debugging

Additionally, `connector()` method on the client instance allows you to access
other capabilities of Saloon connector, such as setting or modifying middleware.
See SaloonPHP documentation for more details:
https://docs.saloon.dev/digging-deeper/middleware

```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Utils\Env;

class User {
    public int $age;
    public string $name;
}

// OpenAI auth params
$yourApiKey = Env::get('OPENAI_API_KEY'); // use your own API key

// Create instance of OpenAI client in debug mode
$client = (new OpenAIClient($yourApiKey))->withDebug();

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
);

dump($user);

assert(isset($user->name));
assert(isset($user->age));
?>
```
