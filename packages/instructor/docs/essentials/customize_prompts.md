---
title: Customize Prompts
description: 'Add system text, prompt text, and cached context.'
---

Instructor builds a structured prompt from several components: system text, user messages,
a mode-specific instruction prompt, examples, and retry context. You can customize most
of these to tune extraction behavior without changing the underlying extraction flow.

The default `StructuredPromptRequestMaterializer` uses prompt classes backed by bundled
Twig templates. Customize the default path by supplying your own prompt classes.

`RequestMaterializer` and its inline prompt/chat-structure settings are deprecated 2.5
compatibility APIs. They have no effect on the default path and work only when that
legacy materializer is injected explicitly.


## System And Prompt Text

The two most common customization points are the system message and the prompt text:

```php
use Cognesy\Instructor\StructuredOutput;

$result = (new StructuredOutput)
    ->withSystem('You are a precise data extraction assistant. Return only factual data.')
    ->withPrompt('Extract the contact details from the text below.')
    ->with(messages: $text, responseModel: Contact::class)
    ->get();
```

- **System text** sets the model's persona and overall behavior. Use it for stable
  instructions that apply across many requests.
- **Prompt text** provides task-specific instructions for this particular extraction.
  On the default structured prompt path it is rendered inside the single system prompt body
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
```


## Examples

Few-shot examples are another prompt component. On the default structured prompt path they
are rendered as markdown inside the system prompt to demonstrate the expected extraction style:

```php
use Cognesy\Instructor\Extras\Example\Example;

$result = (new StructuredOutput)
    ->withExamples([
        Example::fromText('Jane Doe, 31', ['name' => 'Jane Doe', 'age' => 31]),
    ])
    ->with(messages: $text, responseModel: Person::class)
    ->get();
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
```

The cached context is placed before the per-request content in the prompt. On the new
structured prompt path, cached system text, cached task text, and cached examples are
rendered into a cached system prompt and projected through provider-native cached context.
Content passed through `withCachedContext()` is marked with cache control headers where the
provider supports them.


## Mode-Specific Prompts

Instructor uses a default prompt class for each output mode that tells the model how to
format its response. The bundled classes use `.md.twig` templates and are configured in
`StructuredOutputConfig`.

| Mode | Default prompt behavior |
|---|---|
| `Tools` | "Extract correct and accurate data from the input using provided tools." |
| `Json` | Includes the JSON Schema and asks for a strict JSON response |
| `JsonSchema` | Asks for a strict JSON response following the provided schema |
| `MdJson` | Includes the JSON Schema and asks for JSON inside a Markdown code block |

### Overriding Mode Prompts

Use prompt classes as the supported customization seam:

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
```

The defaults are `extracted_data` and `Function call based on user instructions.`
respectively. Overriding them with task-specific values can help the model understand
what the tool represents.

> `OutputMode::Json` and `OutputMode::MdJson` ignore tool name and description since
> they do not use tool calling.


## Retry Prompt

When validation fails and retries are enabled, Instructor renders the configured retry
prompt class:

```php
$config = new StructuredOutputConfig(
    retryPromptClass: App\Prompts\RetryFeedbackPrompt::class,
);
```

The same pattern applies to deserialization repair via `deserializationErrorPromptClass`.


## Legacy Compatibility

The inline `modePrompts`, `retryPrompt`, and `chatStructure` settings are retained only
for applications that explicitly inject the deprecated `RequestMaterializer`. They are
scheduled for removal in 2.6. New code should use prompt classes or provide a custom
`CanMaterializeRequest` implementation when prompt-class customization is insufficient.
