# Providing examples to LLM

To improve the results of LLM inference you can provide examples of the expected output.
This will help LLM to understand the context and the expected structure of the output.

It is typically useful in the `Mode::Json` and `Mode::MdJson` modes, where the output
is expected to be a JSON object.

Instructor's `request()` method accepts an array of examples as the `examples` parameter,
where each example is an instance of the `Example` class.


## `Example` class

`Example` constructor have two main arguments: `input` and `output`.

The `input` property is  a string which describes the input message, while he `output`
property is an array which represents the expected output.

Instructor will append the list of examples to the prompt sent to LLM, with output
array data rendered as JSON text.

```php
<?php
use Cognesy\Instructor\Data\Example;

class User {
    public int $age;
    public string $name;
}

$user = (new Instructor)->respond(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
    examples: [
        new Example(
            input: "John is 50 and works as a teacher.",
            output: ['name' => 'John', 'age' => 50]
        ),
        new Example(
            input: "We have recently hired Ian, who is 27 years old.",
            output: ['name' => 'Ian', 'age' => 27]
        ),
    ],
    mode: Mode::Json
);
?>
```


## Helper methods

You can also create Example instances using the `fromText()`, `fromChat()`, `fromData()`
helper static methods. All of them accept $output as an array of the expected output data
and differ in the way the input data is provided.

`Example::fromText()` method accepts a string as an input. It is equivalent to creating
an instance of Example using the constructor.

`Example::fromChat()` method accepts an array of messages, which may be useful when
you want to use a chat or chat fragment as a demonstration of the input.

`Example::fromData()` method accepts any data type and uses the `Json::encode()` method to
convert it to a string. It may be useful to provide a complex data structure as an example
input.
