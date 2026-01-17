---
title: 'Why Instructor?'
---


LLMs are powerful, but their outputs are unpredictable. Instructor solves this.

## The Problem

You've integrated an LLM into your PHP application. Now what?

```php
<?php
// Typical LLM integration without Instructor
$response = $openai->chat([
    'messages' => [['role' => 'user', 'content' => 'Extract the person name and age from: "John is 25"']]
]);

$text = $response['choices'][0]['message']['content'];
// $text = "The person's name is John and they are 25 years old."
// or "Name: John, Age: 25"
// or "{ name: 'John', age: 25 }"
// or something else entirely...

// Now you need to:
// 1. Parse this somehow
// 2. Handle all possible formats
// 3. Validate the data
// 4. Handle errors
// 5. Retry on failure
// 6. Hope it works
```

**The result?** Fragile code, inconsistent data, and endless edge cases.

## The Solution

Instructor gives you **structured, validated, type-safe outputs**:

```php
<?php
class Person {
    public string $name;
    public int $age;
}

$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMessages('John is 25')
    ->get();

// Always a Person object
// Always with string $name
// Always with int $age
// Validated automatically
// Retries on failure
```

## How It Works

Instructor uses a three-step process:

```
┌─────────────┐     ┌─────────────┐     ┌─────────────┐
│   Define    │ ──▶ │   Extract   │ ──▶ │   Validate  │
│  PHP Class  │     │   via LLM   │     │  & Return   │
└─────────────┘     └─────────────┘     └─────────────┘
```

1. **Define** - You create a PHP class with typed properties
2. **Extract** - Instructor sends your schema to the LLM with optimized prompts
3. **Validate** - Results are validated; failures trigger automatic retry with feedback

## Key Benefits

### 1. Type Safety

Your IDE understands the response. Autocomplete works. Static analysis catches errors.

```php
<?php
$person = (new StructuredOutput)->withResponseClass(Person::class)->get();

// IDE knows $person->name is a string
// IDE knows $person->age is an int
// Typos like $person->naem are caught immediately
```

### 2. Automatic Validation

Use Symfony Validator constraints. Invalid responses trigger automatic retry:

```php
<?php
class Person {
    #[Assert\NotBlank]
    #[Assert\Length(min: 2, max: 100)]
    public string $name;

    #[Assert\Range(min: 0, max: 150)]
    public int $age;
}

// If LLM returns age: -5, Instructor:
// 1. Detects validation failure
// 2. Sends error feedback to LLM
// 3. Requests corrected response
// 4. Repeats until valid or max retries
```

### 3. Self-Correcting Retries

LLMs make mistakes. Instructor handles this gracefully:

```php
<?php
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMessages($text)
    ->withMaxRetries(3)  // Try up to 3 times
    ->get();
```

On validation failure, Instructor tells the LLM exactly what went wrong:

```
"Validation failed: age must be greater than 0. Please correct and try again."
```

### 4. Provider Independence

Write once, run anywhere. Switch LLM providers without changing your code:

```php
<?php
// Development: Use local Ollama
$result = (new StructuredOutput)->using('ollama')->withResponseClass(Task::class)->get();

// Staging: Use Groq for speed
$result = (new StructuredOutput)->using('groq')->withResponseClass(Task::class)->get();

// Production: Use OpenAI for quality
$result = (new StructuredOutput)->using('openai')->withResponseClass(Task::class)->get();
```

### 5. Multiple Output Modes

Works with any model capability:

| Mode | Best For | How It Works |
|------|----------|--------------|
| `Tools` | OpenAI, Claude | Uses function/tool calling |
| `JsonSchema` | GPT-4, newer models | Strict JSON Schema mode |
| `Json` | Most models | JSON response format |
| `MdJson` | Any model | Prompting-based extraction |

### 6. Streaming Support

Get partial results as they arrive:

```php
<?php
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->onPartialUpdate(fn($partial) =>
        echo "Processing: " . ($partial->name ?? '...') . "\n"
    )
    ->with(messages: $text, options: ['stream' => true])
    ->get();
```

### 7. Multimodal Inputs

Process text, images, and chat conversations with the same API:

```php
<?php
// Text
->withMessages("Extract from this text...")

// Images
->withImages(['receipt.jpg'])
->withMessages("Extract line items")

// Chat history
->withMessages([
    ['role' => 'system', 'content' => 'You extract data'],
    ['role' => 'user', 'content' => 'Process this...']
])
```

## Comparison

### Without Instructor

```php
<?php
$response = $client->chat(['messages' => [...]]);
$json = json_decode($response['choices'][0]['message']['content'], true);

if (json_last_error() !== JSON_ERROR_NONE) {
    // Handle JSON parse error
    // Try to extract with regex?
    // Log and retry?
}

if (!isset($json['name']) || !is_string($json['name'])) {
    // Handle missing/invalid field
}

if (!isset($json['age']) || !is_int($json['age'])) {
    // Handle missing/invalid field
}

if ($json['age'] < 0) {
    // Handle validation error
    // Retry somehow?
}

$person = new Person();
$person->name = $json['name'];
$person->age = $json['age'];
```

### With Instructor

```php
<?php
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMessages($text)
    ->get();
```

**Same result. Zero boilerplate.**

## Why Not Just Use JSON Mode / JSON Schema?

"But OpenAI has `response_format: json_object` and strict JSON Schema mode now. Why do I need Instructor?"

Good question. Here's what you're still stuck with:

### 1. Provider Inconsistency

Every provider does it differently:

| Provider | JSON Mode | JSON Schema | Tool Calling |
|----------|:---------:|:-----------:|:------------:|
| OpenAI | `response_format: {type: "json_object"}` | `response_format: {type: "json_schema", ...}` | Yes |
| Anthropic | ❌ No native support | ❌ No native support | Yes (different format) |
| Gemini | Different API entirely | Different API entirely | Yes (different format) |
| Mistral | Partial support | No | Yes |
| Ollama | Model-dependent | Model-dependent | Model-dependent |

**With raw APIs:** You write different code for each provider.

**With Instructor:** One API. Instructor picks the best extraction method automatically.

```php
<?php
// Same code works everywhere
$result = (new StructuredOutput)
    ->using('anthropic')  // or 'openai', 'gemini', 'ollama'...
    ->withResponseClass(Person::class)
    ->get();
```

### 2. No Object Hydration

JSON Schema gives you... JSON. Not objects.

```php
<?php
// OpenAI with JSON Schema
$response = $openai->chat([
    'messages' => [...],
    'response_format' => [
        'type' => 'json_schema',
        'json_schema' => [
            'name' => 'person',
            'schema' => [
                'type' => 'object',
                'properties' => [
                    'name' => ['type' => 'string'],
                    'age' => ['type' => 'integer'],
                ],
                'required' => ['name', 'age'],
            ],
        ],
    ],
]);

$json = json_decode($response['choices'][0]['message']['content'], true);
// $json = ['name' => 'John', 'age' => 25]

// Now you manually hydrate:
$person = new Person();
$person->name = $json['name'];
$person->age = $json['age'];
// For nested objects? More manual work.
// For arrays of objects? Even more.
```

**With Instructor:** Direct to typed objects, including nested structures.

```php
<?php
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->get();
// $person is already a Person object
```

### 3. Schema Definition Hell

JSON Schema is verbose and lives separately from your code:

```php
<?php
// JSON Schema approach - 20+ lines for a simple object
$schema = [
    'type' => 'object',
    'properties' => [
        'name' => [
            'type' => 'string',
            'description' => 'The person\'s full name',
            'minLength' => 1,
        ],
        'age' => [
            'type' => 'integer',
            'description' => 'Age in years',
            'minimum' => 0,
            'maximum' => 150,
        ],
        'email' => [
            'type' => 'string',
            'format' => 'email',
            'description' => 'Contact email',
        ],
    ],
    'required' => ['name', 'age'],
    'additionalProperties' => false,
];
```

**With Instructor:** Your PHP class IS the schema.

```php
<?php
class Person {
    /** The person's full name */
    #[Assert\NotBlank]
    public string $name;

    /** Age in years */
    #[Assert\Range(min: 0, max: 150)]
    public int $age;

    #[Assert\Email]
    public string|null $email;
}
```

Schema and validation rules in one place. IDE autocomplete. Type checking. Refactoring support.

### 4. No Validation Beyond Types

JSON Schema validates structure, not business logic:

```php
<?php
// JSON Schema says this is valid:
{ "name": "", "age": -5, "email": "not-an-email" }
// All correct types! But completely useless data.
```

**With Instructor:** Full validation with Symfony constraints.

```php
<?php
class Person {
    #[Assert\NotBlank]
    #[Assert\Length(min: 2)]
    public string $name;  // Empty string? Rejected.

    #[Assert\Positive]
    public int $age;  // Negative? Rejected.

    #[Assert\Email]
    public string $email;  // Invalid format? Rejected.
}
```

### 5. No Retry Mechanism

JSON Schema mode fails silently or throws. You handle recovery:

```php
<?php
// What happens when the LLM returns invalid JSON despite schema?
try {
    $response = $openai->chat([...]);
    $json = json_decode($response['choices'][0]['message']['content'], true);
} catch (Exception $e) {
    // Now what?
    // Retry with same prompt? Probably same error.
    // Modify the prompt? How?
    // Log and give up?
}
```

**With Instructor:** Automatic retry with error feedback.

```php
<?php
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMaxRetries(3)
    ->get();

// On failure, Instructor tells the LLM:
// "Validation failed: 'age' must be positive. You returned -5. Please correct."
// LLM tries again with that context.
```

### 6. No Streaming Support for Structured Data

JSON Schema mode gives you complete-or-nothing:

```php
<?php
// Can't do this with raw JSON Schema mode:
// - Show partial results as they arrive
// - Update UI progressively
// - Stream array items one by one
```

**With Instructor:** Full streaming with partial updates.

```php
<?php
$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->onPartialUpdate(fn($p) => updateUI($p))
    ->with(messages: $text, options: ['stream' => true])
    ->get();
```

### 7. Anthropic Doesn't Have JSON Mode

Claude is one of the best models, but Anthropic has no native JSON mode:

```php
<?php
// This doesn't exist for Anthropic:
$response = $anthropic->messages([
    'response_format' => ['type' => 'json_object'],  // ❌ Not supported
]);

// You're stuck with:
// - Prompt engineering ("respond only in JSON...")
// - Hoping it complies
// - Parsing whatever comes back
```

**With Instructor:** Works seamlessly with Claude.

```php
<?php
$person = (new StructuredOutput)
    ->using('anthropic')
    ->withResponseClass(Person::class)
    ->get();
// Instructor uses tool calling or optimized prompts automatically
```

### 8. The Real-World Comparison

| Capability | Raw JSON/JSON Schema | Instructor |
|------------|:--------------------:|:----------:|
| Works with all providers | ❌ Different APIs | ✅ Unified |
| Object hydration | ❌ Manual | ✅ Automatic |
| Nested objects | ❌ Manual recursion | ✅ Automatic |
| Business validation | ❌ None | ✅ Full |
| Retry on failure | ❌ Manual | ✅ Automatic |
| Error feedback to LLM | ❌ None | ✅ Built-in |
| Streaming partials | ❌ Not possible | ✅ Supported |
| Type safety in IDE | ❌ None | ✅ Full |
| Schema = Code | ❌ Separate | ✅ Same file |
| Works with Claude | ❌ No JSON mode | ✅ Yes |

### The Bottom Line

JSON Schema mode is a step forward, but it's a **low-level primitive**. You still need to:

- Write provider-specific code
- Manually deserialize to objects
- Implement your own validation
- Build your own retry logic
- Handle streaming yourself
- Maintain schemas separate from code

Instructor handles all of this. You define a PHP class and call `->get()`.

## When to Use Instructor

**Great for:**

- Extracting structured data from unstructured text
- Building forms that accept natural language
- Processing documents (invoices, resumes, contracts)
- Content classification and tagging
- Data transformation pipelines
- Any task requiring reliable LLM output structure

**Not designed for:**

- Open-ended creative writing
- Tasks where free-form text is the desired output
- Simple completions without structure requirements

## The Instructor Family

Instructor exists in multiple languages with consistent APIs:

| Language | Repository |
|----------|------------|
| **PHP** (this) | [cognesy/instructor-php](https://github.com/cognesy/instructor-php) |
| Python (original) | [jxnl/instructor](https://github.com/jxnl/instructor) |
| JavaScript | [instructor-ai/instructor-js](https://github.com/instructor-ai/instructor-js) |
| Elixir | [instructor-ai/instructor-ex](https://github.com/instructor-ai/instructor-ex) |
| Ruby | [instructor-ai/instructor-rb](https://github.com/instructor-ai/instructor-rb) |

---

**Ready to get started?** Jump to the [Getting Started Guide](getting-started) or explore the [Cookbook](/cookbook) for practical examples.
