---
title: 'Why Use Instructor?'
description: 'Why a schema-first approach to LLM output is easier, safer, and more productive.'
---

Large Language Models produce text exceptionally well. Applications, however, almost
always need typed data -- objects, numbers, enums, validated fields. Bridging that gap
by hand means writing fragile parsing code, guessing at JSON shapes, and hoping the model
cooperates.

Instructor closes the gap by letting you declare what you need and handling the rest:
extraction, deserialization, validation, and retries all happen before the result reaches
your code.

```php
use Cognesy\Instructor\StructuredOutput;

final class User {
    public string $name;
    public int $age;
}

$user = (new StructuredOutput)
    ->with(
        messages: 'Jason is 25 years old.',
        responseModel: User::class,
    )
    ->get();

// $user is a fully typed User object -- no parsing, no guessing.
// @doctest id="ae9a"
```


## What You Gain

### Response Models Replace Manual Parsing

Without Instructor, extracting structured data from an LLM means defining verbose
function-call schemas, parsing JSON responses, and mapping fields to your domain objects
by hand. With Instructor, you write a plain PHP class and let the library derive the
schema, call the model, and hydrate the result automatically.

Your code becomes simpler and easier to reason about. The response model _is_ the
documentation of what you expect.

### Validation Before You Trust The Data

LLM output is probabilistic. A model might return a malformed email address, a negative
age, or a value outside an expected set. Instructor validates every response against your
rules before returning it.

Validation uses Symfony validation attributes, so you can apply the same constraints you
already use in the rest of your PHP application:

```php
use Symfony\Component\Validator\Constraints as Assert;

final class UserDetails {
    #[Assert\NotBlank]
    public string $name;

    #[Assert\Email]
    public string $email;
}
// @doctest id="cd53"
```

You can also build fully custom validation logic using Symfony's `#[Assert\Callback]`
annotation. This lets you enforce cross-field rules, business logic, or any constraint
that goes beyond simple attribute checks:

```php
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

final class UserDetails {
    public string $name;
    public int $age;

    #[Assert\Callback]
    public function validateName(ExecutionContextInterface $context, mixed $payload): void {
        if ($this->name !== strtoupper($this->name)) {
            $context->buildViolation('Name must be in uppercase.')
                ->atPath('name')
                ->setInvalidValue($this->name)
                ->addViolation();
        }
    }
}
// @doctest id="227f"
```

### Self-Correcting Retries

When validation fails, Instructor does not simply throw an exception. If retries are
configured, it sends the validation errors back to the LLM as context and asks it to
try again. The model sees exactly which fields failed and why, giving it a strong signal
for correction.

```php
use Cognesy\Instructor\StructuredOutputRuntime;

$runtime = StructuredOutputRuntime::fromDefaults()->withMaxRetries(2);

$user = (new StructuredOutput)
    ->withRuntime($runtime)
    ->with(
        messages: 'You can reach me at jason@gmailcom -- Jason',
        responseModel: UserDetails::class,
    )
    ->get();

// The LLM may initially return "jason@gmailcom". Instructor catches the
// validation failure, feeds the error back, and the model self-corrects
// to "jason@gmail.com" on the next attempt.
// @doctest id="3f8a"
```

This retry loop dramatically improves reliability without any manual intervention.

### Streaming Without Changing The Request Shape

You can stream partial results as the LLM generates tokens. The request definition stays
the same -- you simply read the result differently:

```php
$stream = (new StructuredOutput)
    ->with(messages: 'Jason is 25 years old.', responseModel: User::class)
    ->stream();

foreach ($stream->partials() as $partial) {
    echo $partial->name ?? '...';
}

$user = $stream->lastUpdate();
// @doctest id="58b2"
```

For lists of objects, the `Sequence` wrapper combined with `stream()->sequence()` yields
each completed item as soon as it is ready, so your application can begin processing
before the full response arrives.

### A Provider-Agnostic API

Instructor works with OpenAI, Anthropic, Google, Azure, and other providers through the
Polyglot inference layer. Switching providers is a configuration change, not a code
rewrite:

```php
// OpenAI
$user = StructuredOutput::using('openai')
    ->with(messages: 'Jason is 25 years old.', responseModel: User::class)
    ->get();

// Anthropic
$user = StructuredOutput::using('anthropic')
    ->with(messages: 'Jason is 25 years old.', responseModel: User::class)
    ->get();
// @doctest id="f086"
```

Your response models, validation rules, and application logic remain identical regardless
of which LLM provider backs the request.


## The Workflow At A Glance

Working with Instructor follows a consistent three-step pattern.

**Step 1: Define the data model.** Create a PHP class with typed public properties that
maps to the information you want to extract:

```php
final class Lead {
    public string $name;
    public string $company;
    public string $email;
}
// @doctest id="f672"
```

**Step 2: Extract.** Pass your input and the response model to `StructuredOutput`:

```php
$lead = (new StructuredOutput)
    ->with(
        messages: $emailBody,
        responseModel: Lead::class,
    )
    ->get();
// @doctest id="8ce7"
```

**Step 3: Use the result.** The returned object is fully typed, validated, and ready to
use in your application -- no additional parsing required:

```php
echo $lead->name;    // "Jason Liu"
echo $lead->company; // "Acme Corp"
echo $lead->email;   // "jason@acme.com"
// @doctest id="2d4b"
```


## When Instructor Is A Good Fit

Instructor works well when you need to:

- Extract structured records from unstructured text (emails, documents, chat logs)
- Classify or label content into predefined categories
- Transform one structured format into another via an LLM
- Generate data that must conform to a strict schema
- Build pipelines where the output of one LLM step is the typed input to the next

If your use case involves free-form text generation where structure is not important,
you may not need Instructor at all. But whenever your application consumes the LLM
output as data rather than prose, a schema-first approach will save you time and reduce
errors.
