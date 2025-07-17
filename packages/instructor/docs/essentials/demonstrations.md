## Providing examples to LLM

To improve the results of LLM inference you can provide examples of the expected output.
This will help LLM to understand the context and the expected structure of the output.

It is typically useful in the `OutputMode::Json` and `OutputMode::MdJson` modes, where the output
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
use Cognesy\Instructor\Extras\Example\Example;

class User {
    public int $age;
    public string $name;
}

$user = (new StructuredOutput)->with(
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
    mode: OutputMode::Json
)->get();
?>
```

## Modifying the example template

You can use a template string as an input for the Example class. The template string
may contain placeholders for the input data, which will be replaced with the actual
values during the execution.

Currently, the following placeholders are supported:
 - `{input}` - replaced with the actual input message
 - `{output}` - replaced with the actual output data

In case input or output data is an array, Instructor will automatically convert it to
a JSON string before replacing the placeholders.

```php
$user = (new StructuredOutput)->with(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
    examples: [
        new Example(
            input: "John is 50 and works as a teacher.",
            output: ['name' => 'John', 'age' => 50],
            template: "EXAMPLE:\n{input} => {output}\n",
        ),
    ],
    mode: OutputMode::Json
)->get();
```


## Convenience factory methods

You can also create Example instances using the `fromText()`, `fromChat()`, `fromData()`
helper static methods. All of them accept $output as an array of the expected output data
and differ in the way the input data is provided.

### Make example from text

`Example::fromText()` method accepts a string as an input. It is equivalent to creating
an instance of Example using the constructor.

```php
$example = Example::fromText(
    input: 'Ian is 27 yo',
    output: ['name' => 'Ian', 'age' => 27]
);
```

### Make example from chat

`Example::fromChat()` method accepts an array of messages, which may be useful when
you want to use a chat or chat fragment as a demonstration of the input.

```php
$example = Example::fromChat(
    input: [['role' => 'user', 'content' => 'Ian is 27 yo']],
    output: ['name' => 'Ian', 'age' => 27]
);
```

### Make example from data

`Example::fromData()` method accepts any data type and uses the `Json::encode()` method to
convert it to a string. It may be useful to provide a complex data structure as an example
input.

```php
$example = Example::fromData(
    input: ['firstName' => 'Ian', 'lastName' => 'Brown', 'birthData' => '1994-01-01'],
    output: ['name' => 'Ian', 'age' => 27]
);
```
