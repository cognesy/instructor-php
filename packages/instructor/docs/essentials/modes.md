## Extraction modes

Instructor supports several ways to extract data from the response.

### Output Modes

Instructor supports multiple output modes to allow working with various models depending on their capabilities.
- `OutputMode::Json` - generate structured output via LLM's native JSON generation
- `OutputMode::JsonSchema` - use LLM's strict JSON Schema mode to enforce JSON Schema
- `OutputMode::Tools` - use tool calling API to get LLM follow provided schema
- `OutputMode::MdJson` - use prompting to generate structured output; fallback for the models that do not support JSON generation or tool calling

Additionally, you can use `Text` and `Unrestricted` modes to get LLM to generate text output without any structured data extraction.

Those modes are not useful for `StructuredOutput` class (as it is focused on structured output generation) but can be used with `Inference` class.

- `OutputMode::Text` - generate text output
- `OutputMode::Unrestricted` - generate unrestricted output based on inputs provided by the user (with no enforcement of specific output format)

### Example of Using Modes

Mode can be set via parameter of `StructuredOutput::create()` method.

The default mode is `OutputMode::Tools`, which leverages OpenAI-style tool calls.

```php
<?php
use Cognesy\Instructor\StructuredOutput;

$structuredOutput = new StructuredOutput();

$response = $structuredOutput->with(
    messages: "...",
    responseModel: ...,
    ...,
    mode: OutputMode::Json
)->get();
```
Mode can be also set via `request()` method.

```php
<?php
$response = $structuredOutput->request(
    messages: "...",
    responseModel: ...,
    ...,
    mode: OutputMode::Json
)->get();
```

## Modes

### `OutputMode::Tools`

This mode is the default one. It uses OpenAI tools to extract data from the
response.

It is the most reliable mode, but not all models and API providers support it -
check their documentation for more information.

 - https://platform.openai.com/docs/guides/function-calling
 - https://docs.anthropic.com/en/docs/build-with-claude/tool-use
 - https://docs.mistral.ai/capabilities/function_calling/


### `OutputMode::Json`

In this mode Instructor provides response format as JSONSchema and asks LLM
to respond with JSON object following provided schema.

It is supported by many open source models and API providers - check their
documentation.

See more about JSON mode in:

 - https://platform.openai.com/docs/guides/text-generation/json-mode
 - https://docs.anthropic.com/en/docs/test-and-evaluate/strengthen-guardrails/increase-consistency
 - https://docs.mistral.ai/capabilities/json_mode/


### `OutputMode::JsonSchema`

In contrast to `OutputMode::Json` which may not always manage to meet the schema requirements,
`OutputMode::JsonSchema` is strict and guarantees the response to be a valid JSON object that matches
the provided schema.

It is currently supported only by new OpenAI models (check their docs for details).

NOTE: OpenAI JsonSchema mode does not support optional properties. If you need to have optional
properties in your schema, use `OutputMode::Tools` or `OutputMode::Json`.

See more about JSONSchema mode in:

 - https://platform.openai.com/docs/guides/structured-outputs


### `OutputMode::MdJson`

In this mode Instructor asks LLM to answer with JSON object following provided schema and
return answer as Markdown codeblock.

It may improve the results for LLMs that have not been finetuned to respond with JSON
as they are likely to be already trained on large amounts of programming docs and have
seen a lot of properly formatted JSON objects within MD codeblocks.
