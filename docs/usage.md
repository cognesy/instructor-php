## Basic usage

This is a simple example demonstrating how Instructor retrieves structured information from provided text (or chat message sequence).

Response model class is a plain PHP class with typehints specifying the types of fields of the object.

> NOTE: By default, Instructor looks for OPENAI_API_KEY environment variable to get
> your API key. You can also provide the API key explicitly when creating the
> Instructor instance.

```php
<?php
use Cognesy\Instructor\Instructor;

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
use Cognesy\Instructor\Instructor;

$value = (new Instructor)->respond(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
);
```


## Alternative way to get results

You can call `request()` method to initiate Instructor with request data
and then call `get()` to get the response.

```php
use Cognesy\Instructor\Instructor;

$instructor = (new Instructor)->request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
);

$person = $instructor->get();
```


### Structured-to-structured data processing

Instructor offers a way to use structured data as an input. This is
useful when you want to use object data as input and get another object
with a result of LLM inference.

The `input` field of Instructor's `respond()` and `request()` methods
can be an object, but also an array or just a string.

```php
<?php
use Cognesy\Instructor\Instructor;

class Email {
    public function __construct(
        public string $address = '',
        public string $subject = '',
        public string $body = '',
    ) {}
}

$email = new Email(
    address: 'joe@gmail',
    subject: 'Status update',
    body: 'Your account has been updated.'
);

$translation = (new Instructor)->respond(
    input: $email,
    responseModel: Email::class,
    prompt: 'Translate the text fields of email to Spanish. Keep other fields unchanged.',
);
?>
```



## Streaming support

Instructor supports streaming of partial results, allowing you to start
processing the data as soon as it is available.

```php
<?php
use Cognesy\Instructor\Instructor;

$stream = (new Instructor)->request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
    options: ['stream' => true]
)->stream();

foreach ($stream->partials() as $partialPerson) {
    // process partial person data
    echo "Name: " $partialPerson->name ?? '...';
    echo "Age: " $partialPerson->age ?? '...';
}

// after streaming is done you can get the final, fully processed person object...
$person = $stream->getLastUpdate()
// ...to, for example, save it to the database
$db->save($person);
?>
```


## Scalar responses

See [Scalar responses](scalars.md) for more information on how to generate scalar responses with `Scalar` adapter class.


## Partial responses and streaming

See [Streaming and partial updates](partials.md) for more information on how to work with partial updates and streaming.


## Extracting arguments for function call

See [FunctionCall helper class](function_calls.md) for more information on how to extract arguments for callable objects.
```
