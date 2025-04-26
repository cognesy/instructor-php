## Extraction modes

Instructor supports several ways to extract data from the response. The default mode is `Mode::Tools`, which leverages OpenAI-style tool calls.

Mode can be set via parameter of `Instructor::response()` or `Instructor::request()`
methods.

```php
<?php
use Cognesy\Instructor\Instructor;

$instructor = new Instructor();

$response = $instructor->respond(
    messages: "...",
    responseModel: ...,
    ...,
    mode: Mode::Json
);
```
Mode can be also set via `request()` method.

```php
<?php
$response = $instructor->request(
    messages: "...",
    responseModel: ...,
    ...,
    mode: Mode::Json
)->get();
```

## Modes

### `Mode::Tools`

This mode is the default one. It uses OpenAI tools to extract data from the
response.

It is the most reliable mode, but not all models and API providers support it -
check their documentation for more information.

 - https://platform.openai.com/docs/guides/function-calling
 - https://docs.anthropic.com/en/docs/build-with-claude/tool-use
 - https://docs.mistral.ai/capabilities/function_calling/


### `Mode::Json`

In this mode Instructor provides response format as JSONSchema and asks LLM
to respond with JSON object following provided schema.

It is supported by many open source models and API providers - check their
documentation.

See more about JSON mode in:

 - https://platform.openai.com/docs/guides/text-generation/json-mode
 - https://docs.anthropic.com/en/docs/test-and-evaluate/strengthen-guardrails/increase-consistency
 - https://docs.mistral.ai/capabilities/json_mode/


### `Mode::JsonSchema`

In contrast to `Mode::Json` which may not always manage to meet the schema requirements,
`Mode::JsonSchema` is strict and guarantees the response to be a valid JSON object that matches
the provided schema.

It is currently supported only by new OpenAI models (check their docs for details).

NOTE: OpenAI JsonSchema mode does not support optional properties. If you need to have optional
properties in your schema, use `Mode::Tools` or `Mode::Json`.

See more about JSONSchema mode in:

 - https://platform.openai.com/docs/guides/structured-outputs


### `Mode::MdJson`

In this mode Instructor asks LLM to answer with JSON object following provided schema and
return answer as Markdown codeblock.

It may improve the results for LLMs that have not been finetuned to respond with JSON
as they are likely to be already trained on large amounts of programming docs and have
seen a lot of properly formatted JSON objects within MD codeblocks.
