# Support for OpenAI API


Mode compatibility:
 - Mode::Tools (recommended)
 - Mode::Json
 - Mode::MdJson


```php
<?php
$loader = require 'vendor/autoload.php';
$loader->add('Cognesy\\Instructor\\', __DIR__ . '../../src/');

use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;
use Cognesy\Instructor\Utils\Env;

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

// Mistral instance params
$yourApiKey = Env::get('OPENAI_API_KEY');

// Create instance of OpenAI client initialized with custom parameters
$client = new OpenAIClient($yourApiKey);

$functions = [
    'type' => 'function',
    'function' => [
        'name' => 'get_temperature',
        'description' => 'Gets temperature for a given location in a given unit.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'location' => [
                    'type' => 'string'
                ],
                'location_timezone' => [
                    'type' => 'string'
                ],
                'unit' => [
                    'type' => 'string'
                ],
            ],
            'required' => ['location', 'timezone', 'unit']
        ]
    ]
];

$response = $client
    ->wiretap(fn($event) => $event->print())
    ->jsonCompletion(
        messages: [
            ["role" => "user", "content" => "Call the sensor to check the temperature in Warsaw in Celsius degrees. Respond with JSON object."],
            // ["role" => "user", "content" => "Call the sensor to check the temperature in Warsaw in Celsius degrees. Respond with JSON object wrapped in ```json``` tags."],
        ],
        model: 'gpt-3.5-turbo',
        //options: ['stream' => true]
    )->respond();

//$response = $client
//    ->wiretap(fn($event) => $event->print())
//    ->jsonCompletion(
//        messages: [
//            ["role" => "user", "content" => "Call the sensor to check the temperature in Warsaw in Celsius degrees. Respond with JSON object."],
//        ],
//        model: 'gpt-3.5-turbo',
//        options: ['stream' => true]
//    )->streamAll();

//$response = $client
//    ->wiretap(fn($event) => $event->print())
//    ->toolsCall(
//        messages: [
//            ["role" => "user", "content" => "Call the sensor to check the temperature in Warsaw in Celsius degrees. Respond with JSON object."],
//        ],
//        model: 'gpt-3.5-turbo',
//        toolChoice: ["type" => "function", "function" => ["name" => "get_temperature"]],
//        tools: [$functions],
//        options: ['stream' => true]
//    )->streamAll();

dump($response);
?>
```
