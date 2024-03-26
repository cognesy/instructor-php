## Basic usage

This is a simple example demonstrating how Instructor retrieves structured information from provided text (or chat message sequence).

Response model class is a plain PHP class with typehints specifying the types of fields of the object.

> NOTE: By default, Instructor looks for OPENAI_API_KEY environment variable to get
> your API key. You can also provide the API key explicitly when creating the
> Instructor instance.

```php
<?php
use Cognesy\Instructor;

// Step 0: Create .env file in your project root:
// OPENAI_API_KEY=your_api_key

// Step 1: Define target data structure(s)
class Person {
    public string $name;
    public int $age;
}

// Step 2: Provide content to process
$text = "His name is Jason and he is 28 years old.";

// Step 3: Use Instructor to run LLM inference
$person = (new Instructor)->respond(
    messages: [['role' => 'user', 'content' => $text]],
    responseModel: Person::class,
);

// Step 4: Work with structured response data
assert($person instanceof Person); // true
assert($person->name === 'Jason'); // true
assert($person->age === 28); // true

echo $person->name; // Jason
echo $person->age; // 28

var_dump($person);
// Person {
//     name: "Jason",
//     age: 28
// }    
```

!!! note

    Currently, Instructor for PHP only supports classes / objects as response models. In case you want to extract simple types or arrays, you need to wrap them in a class.



## String as Input

You can provide a string instead of an array of messages. This is useful when you want to extract data from a single block of text and want to keep your code simple.

```php
use Cognesy\Instructor;

$value = (new Instructor)->respond(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
);
```


## Alternative ways to call Instructor

You can call `request()` method to set the parameters of the request and then call `get()` to get the response.

```php
use Cognesy\Instructor;
$instructor = (new Instructor)->request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
);
$person = $instructor->get();
```

You can also initialize Instructor with a request object.

```php
use Cognesy\Instructor;
use Cognesy\Instructor\Data\Request;

$instructor = (new Instructor)->withRequest(new Request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
))->get();
```

## Custom OpenAI client

You can provide your own OpenAI client to Instructor. This is useful when you want to initialize OpenAI client with custom values - e.g. to call other LLMs which support OpenAI API.

```php
use Cognesy\Instructor\Instructor;
use Cognesy\Instructor\Clients\OpenAI\OpenAIClient;

class User {
    public int $age;
    public string $name;
}

/ Create instance of OpenAI client initialized with custom parameters for Ollama
$client = new OpenAIClient(
    apiKey: 'ollama',
    baseUri: 'http://localhost:11434/v1',
    connectTimeout: 3,
    requestTimeout: 60, // set based on your machine performance :)
);

/// Get Instructor with the default client component overridden with your own
$instructor = (new Instructor)->withClient($client);

$user = $instructor->respond(
    messages: "Jason (@jxnlco) is 25 years old and is the admin of this project. He likes playing football and reading books.",
    responseModel: User::class,
    model: 'llama2',
    mode: Mode::MdJson
    //options: ['stream' => true ]
);

dump($user);
```
