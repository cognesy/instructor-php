---
title: 'Customize Prompts'
description: 'Add system text, prompt text, and cached context.'
---

Instructor builds a structured prompt from several components: system text, user messages,
a mode-specific instruction prompt, examples, and retry context. You can customize most
of these to tune extraction behavior without changing the underlying extraction flow.

There are currently two prompt materializers in the package:

- `RequestMaterializer` is the legacy/default path
- `StructuredPromptRequestMaterializer` is the new path using prompt classes and markdown templates

Both can be selected through `StructuredOutputRuntime::withRequestMaterializer()`.


## System And Prompt Text

The two most common customization points are the system message and the prompt text:

```php
use Cognesy\Instructor\StructuredOutput;

$result = (new StructuredOutput)
    ->withSystem('You are a precise data extraction assistant. Return only factual data.')
    ->withPrompt('Extract the contact details from the text below.')
    ->with(messages: $text, responseModel: Contact::class)
    ->get();
// @doctest id="6792"
```

- **System text** sets the model's persona and overall behavior. Use it for stable
  instructions that apply across many requests.
- **Prompt text** provides task-specific instructions for this particular extraction.
  On the new structured prompt path it is rendered inside the single system prompt body
  alongside the mode-specific extraction instructions.

You can also pass both through the `with()` method:

```php
$result = (new StructuredOutput)
    ->with(
        messages: $text,
        responseModel: Contact::class,
        system: 'Return concise, accurate data.',
        prompt: 'Extract the contact details.',
    )
    ->get();
// @doctest id="f008"
```


## Examples

Few-shot examples are another prompt component. On the new structured prompt path they
are rendered as markdown inside the system prompt to demonstrate the expected extraction style:

```php
use Cognesy\Instructor\Extras\Example\Example;

$result = (new StructuredOutput)
    ->withExamples([
        Example::fromText('Jane Doe, 31', ['name' => 'Jane Doe', 'age' => 31]),
    ])
    ->with(messages: $text, responseModel: Person::class)
    ->get();
// @doctest id="092d"
```

See the [Demonstrations](demonstrations.md) page for details on the `Example` class.


## Cached Context

Some providers (notably Anthropic) support prompt caching, where stable parts of the
conversation are cached between requests to reduce latency and cost. Use
`withCachedContext()` to mark content as cacheable:

```php
$result = (new StructuredOutput)
    ->withCachedContext(
        messages: $referenceDocument,
        system: 'You are a document analyst.',
        prompt: 'Extract entities from the document.',
        examples: $examples,
    )
    ->with(messages: 'Now extract from this specific paragraph...', responseModel: Entity::class)
    ->get();
// @doctest id="7989"
```

The cached context is placed before the per-request content in the prompt. On the new
structured prompt path, cached system text, cached task text, and cached examples are
rendered into a cached system prompt and projected through provider-native cached context.
Content passed through `withCachedContext()` is marked with cache control headers where the
provider supports them.


## Mode-Specific Prompts

Instructor uses a default prompt for each output mode that tells the model how to format
its response. On the legacy path these prompts are inline strings. On the new path they
are prompt classes backed by markdown templates and configured in `StructuredOutputConfig`.

| Mode | Default prompt behavior |
|---|---|
| `Tools` | "Extract correct and accurate data from the input using provided tools." |
| `Json` | Includes the JSON Schema and asks for a strict JSON response |
| `JsonSchema` | Asks for a strict JSON response following the provided schema |
| `MdJson` | Includes the JSON Schema and asks for JSON inside a Markdown code block |

### Overriding Mode Prompts

Legacy inline prompt override:

```php
use Cognesy\Instructor\Config\StructuredOutputConfig;
use Cognesy\Instructor\Enums\OutputMode;

$config = new StructuredOutputConfig(
    modePrompts: [
        OutputMode::Tools->value => 'Use the provided tool to extract data accurately.',
        OutputMode::Json->value => "Respond with a JSON object matching this schema:\n<|json_schema|>\n",
    ],
);
// @doctest id="8102"
```

New prompt-class override:

```php
$config = new StructuredOutputConfig(
    modePromptClasses: [
        OutputMode::Tools->value => App\Prompts\ToolsSystemPrompt::class,
        OutputMode::Json->value => App\Prompts\JsonSystemPrompt::class,
        OutputMode::JsonSchema->value => App\Prompts\JsonSchemaSystemPrompt::class,
        OutputMode::MdJson->value => App\Prompts\MdJsonSystemPrompt::class,
    ],
    retryPromptClass: App\Prompts\RetryFeedbackPrompt::class,
    deserializationErrorPromptClass: App\Prompts\DeserializationRepairPrompt::class,
);
// @doctest id="c1c6"
```

If you store these in YAML, use FQN strings:

```yaml
modePromptClasses:
  tool_call: 'App\\Prompts\\ToolsSystemPrompt'
  json: 'App\\Prompts\\JsonSystemPrompt'
  json_schema: 'App\\Prompts\\JsonSchemaSystemPrompt'
  md_json: 'App\\Prompts\\MdJsonSystemPrompt'

retryPromptClass: 'App\\Prompts\\RetryFeedbackPrompt'
deserializationErrorPromptClass: 'App\\Prompts\\DeserializationRepairPrompt'
# @doctest id="2bc1"
```

### Template Placeholders

Mode prompts support the `<|json_schema|>` placeholder, which Instructor replaces with
the JSON Schema generated from your response model. This is particularly important for
`Json` and `MdJson` modes, where the schema must be embedded in the prompt:

```php
$config = new StructuredOutputConfig(
    modePrompts: [
        OutputMode::Json->value => "Your task is to respond with a JSON object. "
            . "Response must follow this JSON Schema:\n<|json_schema|>\n",
    ],
);
// @doctest id="a061"
```


## Tool Name And Description

In `OutputMode::Tools`, the tool definition sent to the model includes a name and
description. These provide semantic context that can improve extraction quality:

```php
use Cognesy\Instructor\Config\StructuredOutputConfig;

$config = new StructuredOutputConfig(
    toolName: 'extract_person',
    toolDescription: 'Extract personal information from the provided text.',
);
// @doctest id="fd6e"
```

The defaults are `extracted_data` and `Function call based on user instructions.`
respectively. Overriding them with task-specific values can help the model understand
what the tool represents.

> `OutputMode::Json` and `OutputMode::MdJson` ignore tool name and description since
> they do not use tool calling.


## Retry Prompt

When validation fails and retries are enabled, Instructor appends a retry prompt to the
conversation. The default is:

```
JSON generated incorrectly, fix following errors:
// @doctest id="2320"
```

Legacy inline retry prompt override:

```php
$config = new StructuredOutputConfig(
    retryPrompt: 'The previous response had validation errors. Please correct them:',
);
// @doctest id="bde4"
```

New prompt-class override:

```php
$config = new StructuredOutputConfig(
    retryPromptClass: App\Prompts\RetryFeedbackPrompt::class,
);
// @doctest id="8152"
```

The same pattern applies to deserialization repair via `deserializationErrorPromptClass`.


## Chat Structure

Instructor assembles the final prompt from named sections in a specific order. The default
structure includes sections for system messages, cached context, prompt, examples,
messages, and retries. You can reorder or extend this through `StructuredOutputConfig`:

```php
$config = new StructuredOutputConfig(
    chatStructure: [
        'system',
        'pre-cached', 'cached-prompt', 'cached-examples', 'cached-messages', 'post-cached',
        'pre-prompt', 'prompt', 'post-prompt',
        'pre-examples', 'examples', 'post-examples',
        'pre-messages', 'messages', 'post-messages',
        'pre-retries', 'retries', 'post-retries',
    ],
);
// @doctest id="74d2"
```

Most applications will never need to modify the chat structure. It is exposed for
advanced use cases where you need precise control over prompt ordering.
