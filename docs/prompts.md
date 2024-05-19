# Customizing prompts

In case you want to take control over the prompts sent by Instructor
to LLM for different modes, you can use the `prompt` parameter in the
`request()` or `respond()` methods.

It will override the default Instructor prompts, allowing you to fully
customize how LLM is instructed to process the input.


## Prompting models with tool calling support

`Mode::Tools` is usually most reliable way to get structured outputs following
provided response schema.

`Mode::Tools` can make use of `$toolName` and `$toolDescription` parameters
to provide additional semantic context to the LLM, describing the tool to be used
for processing the input. `Mode::Json` and `Mode::MdJson` ignore these parameters,
as tools are not used in these modes.

```php
<?php
$user = $instructor
    ->request(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to extract correct and accurate data from the messages using provided tools.\n",
        toolName: 'extract',
        toolDescription: 'Extract information from provided content',
        mode: Mode::Tools)
    ->get();
?>
```


## Prompting models supporting JSON output

Aside from tool calling Instructor supports two other modes for getting structured
outputs from LLM: `Mode::Json` and `Mode::MdJson`.

`Mode::Json` uses JSON mode offered by some models and API providers to get LLM
respond in JSON format rather than plain text.

```php
<?php
$user = $instructor->respond(
    messages: "Our user Jason is 25 years old.",
    responseModel: User::class,
    prompt: "\nYour task is to respond correctly with JSON object.",
    mode: Mode::Json
);
?>
```
Note that various models and API providers have specific requirements
on the input format, e.g. for OpenAI JSON mode you are required to include
`JSON` string in the prompt.


## Including JSON Schema in the prompt

Instructor takes care of automatically setting the `response_format`
parameter, but this may not be sufficient for some models or providers -
some of them require specifying JSON response format as part of the
prompt, rather than just as `response_format` parameter in the request
(e.g. OpenAI).

For this reason, when using Instructor's `Mode::Json` and `Mode::MdJson`
consider including the expected JSON Schema in the prompt. Otherwise, the
response is unlikely to match your target model, making it impossible for
Instructor to deserialize it correctly.

Instructor provides a helper method `createJsonSchema()` that generates
a JSON Schema for given `responseModel` input. It accepts the same parameters
as `$responseModel` parameter of `request()` and `respond()` methods and
returns JSON Schema array. You can use `json_encode()` to convert it to
a JSON string. Alternatively, you can call `createJsonSchemaString()` method
that returns a JSON string directly.

```php
<?php
$jsonSchema = $instructor->createJsonSchemaString(User::class);

$user = $instructor
    ->request(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with JSON object. Response must follow JSONSchema:\n" . $jsonSchema,
        mode: Mode::Json)
    ->get();
?>
```


## Prompt as template

Instructor allows you to use a template string as a prompt. You can use
`{variable}` placeholders in the template string, which will be replaced
with the actual values during the execution.

Currently, the following placeholders are supported:
 - `{json_schema}` - replaced with the JSON Schema for current response model

Example below demonstrates how to use a template string as a prompt:

```php
<?php
$user = $instructor
    ->request(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with JSON object. Response must follow JSONSchema:\n{json_schema}\n",
        mode: Mode::Json)
    ->get();
?>
```


## Prompting the models with no support for tool calling or JSON output

`Mode::MdJson` is the most basic (and least reliable) way to get structured
outputs from LLM. Still, you may want to use it with the models which do not
support tool calling or JSON output.

`Mode::MdJson` relies on the prompting to get LLM response in JSON formatted data.

Many models prompted in this mode will respond with a mixture of plain text and JSON
data. Instructor will try to find JSON data fragment in the response and ignore
the rest of the text.

This approach is most prone to deserialization and validation errors and needs
providing JSON Schema in the prompt to increase the probability that the response
is correctly structured and contains the expected data.

```php
<?php
$user = $instructor
    ->request(
        messages: "Our user Jason is 25 years old.",
        responseModel: User::class,
        prompt: "\nYour task is to respond correctly with strict JSON object containing extracted data within a ```json {} ``` codeblock. Object must validate against this JSONSchema:\n{json_schema}\n",
        mode: Mode::MdJson)
    ->get();
?>
```
