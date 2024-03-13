## Basic usage

This is a simple example demonstrating how Instructor retrieves structured information from provided text (or chat message sequence).

Response model class is a plain PHP class with typehints specifying the types of fields of the object.

```php
<?php

use Cognesy\Instructor;
use OpenAI;

// Step 1: Define target data structure(s)
class Person {
    public string $name;
    public int $age;
}

// Step 2: Provide content to process
$text = "His name is Jason and he is 28 years old.";

// Step 3: Use Instructor to run LLM inference
// NOTE: default OpenAI client is used, needs .env file with OPENAI_API_KEY
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

## Shortcuts

### String as Input

You can provide a string instead of an array of messages. This is useful when you want to extract data from a single block of text and want to keep your code simple.

```php
use Cognesy\Instructor;

$value = (new Instructor)->respond(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
);
```


### Extracting Scalar Values

Sometimes we just want to get quick results without defining a class for the response model, especially if we're trying to get a straight, simple answer in a form of string, integer, boolean or float. Instructor provides a simplified API for such cases.

```php
use Cognesy\Instructor;

$value = (new Instructor)->respond(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Scalar::integer('age'),
);

var_dump($value);
// int(28)
```

In this example, we're extracting a single integer value from the text. You can also use `Scalar::string()`, `Scalar::boolean()` and `Scalar::float()` to extract other types of values.

Additionally, you can use Scalar adapter to extract one of the provided options.

```php
use Cognesy\Instructor;

$value = (new Instructor)->respond(
    messages: "His name is Jason, he currently plays Doom Eternal.",
    responseModel: Scalar::select(
        name: 'activityType',
        options: ['work', 'entertainment', 'sport', 'other']
    ),
);

var_dump($value);
// string(4) "entertainment"
```

NOTE: Currently Scalar::select() always returns strings and its ```options``` parameter only accepts string values.


### Alternative ways to call Instructor

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
use Cognesy\Instructor\Core\Data\Request;

$instructor = (new Instructor)->withRequest(new Request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
))->get();
```

### Partial results

You can define `onPartialUpdate()` callback to receive partial results that can be used to start updating UI before LLM completes the inference. 

> NOTE: Partial updates are not validated. The response is only validated after it is fully received.

```php
use Cognesy\Instructor;

function updateUI($person) {
    // Here you get partially completed Person object update UI with the partial result
}

$person = (new Instructor)->request(
    messages: "His name is Jason, he is 28 years old.",
    responseModel: Person::class,
)->onPartialUpdate(
    fn($partial) => updateUI($partial)
)->get();

// Here you get completed and validated Person object
$this->db->save($person); // ...for example: save to DB
```
