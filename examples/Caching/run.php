# Caching


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
$client = (new OpenAIClient($yourApiKey));

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->request(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
)->get();

dump($user);
echo "Time elapsed (no cache, default): ".$instructor->elapsedTime()." seconds\n\n";

$user2 = $instructor->request(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
    options: ['cache' => true],
)->get();

dump($user2);
echo "Time elapsed (cache on, 1st call): ".$instructor->elapsedTime()." seconds\n\n";

$user3 = $instructor->request(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
    options: ['cache' => true],
)->get();

dump($user3);
echo "Time elapsed (cache on, 2nd call): ".$instructor->elapsedTime()." seconds\n\n";

$user4 = $instructor->request(
    messages: "Jason is 25 years old.",
    responseModel: User::class,
    options: ['cache' => false],
)->get();

dump($user4);
echo "Time elapsed (cache turned off again): ".$instructor->elapsedTime()." seconds\n\n";

?>
```
