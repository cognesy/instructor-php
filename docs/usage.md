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

    Currently, Instructor for PHP only supports classes / objects as response models. In case you want to extract simple types or arrays, you need to wrap them in a class (or use `Scalar` helper class).



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

## Scalar responses

See [Scalar responses](scalars.md) for more information on how to generate scalar responses with `Scalar` adapter class.


## Partial responses and streaming

See [Streaming and partial updates](partials.md) for more information on how to work with partial updates and streaming.
