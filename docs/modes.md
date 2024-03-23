# Extraction modes

Instructor supports several ways to extract data from the response. The default mode is `Mode::Tools`, which leverages OpenAI tool calls.

Mode can be set via parameter of `Instructor::response()` or `Instructor::request()`
methods.

```php
use Cognesy\Instructor\Instructor;

$instructor = new Instructor();

$response = $instructor->respond(
    messages: "...",
    responseModel: ...,
    ...,
    mode: Mode::Json
);
```
Mode can be also set in `Request` object, if you are building it manually for 
Instructor.

```php


$request = new Request(
    messages: "...",
    responseModel: ...,
    ...,
    mode: Mode::Json
);

$response = $instructor->withRequest($request)->get();
```

## Modes

### `Mode::Tools`

This mode is the default one. It uses OpenAI tools to extract data from the response. It is the most reliable mode, but currently only available for OpenAI and Azure OpenAI LLMs.

### `Mode::ParallelTools`

**Not yet implemented.** It will use OpenAI parallel tool calling to return multiple
relevant response models in a single call.

### `Mode::Json`

This mode uses OpenAI JSON mode. See `response_mode` in (OpenAI API Reference)[https://platform.openai.com/docs/api-reference/chat/create]

### `Mode::MdJson`

In this mode Instructor asks LLM to answer with JSON object following provided schema and
return answer as Markdown codeblock.

It may improve the results for LLMs that have not been finetuned to respond with JSON
as they are likely to be already trained on large amounts of programming docs and have
seen a lot of properly formatted JSON objects within MD codeblocks.

### `Mode::Functions`

**Not likely to be implemented.** OpenAI deprecated this mode. To be researched if it is
used by any OS models (which would justify the effort).

### `Mode::Yaml`

**Not implemented.** To be researched as a potential alternative to MdJson mode. Robustness
vs MdJson mode is to be evaluated, but it may be easier for smaller LLMs to return correct
data in this format due to simpler syntax.
