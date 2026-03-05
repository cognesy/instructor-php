---
title: 'Packages'
---

## Start Here

**Most PHP developers need just one package:**

```bash
composer require cognesy/instructor-php
```

This gives you everything: structured output extraction, validation, retries, and support for all major LLM providers. You're ready to go.

---

## When You Need More Control

Instructor is built on a modular architecture. If you need to work at a lower level or integrate with specific frameworks, these packages are available separately.

### The Stack

![Instructor Stack](images/instructor-diagram.png)

---

## Package Details

### Instructor

**The main package. Start here.**

Structured data extraction powered by LLMs. Define a PHP class with typed properties, pass it to Instructor with some text, get a validated object back.

```php
<?php
class Person {
    public string $name;
    public int $age;
}

$person = (new StructuredOutput)
    ->withResponseClass(Person::class)
    ->withMessages("John is 25 years old")
    ->get();

// $person->name = "John"
// $person->age = 25
```

**Why use it:**
- Type-safe outputs (your IDE understands the response)
- Automatic validation with Symfony Validator
- Self-correcting retries (LLM gets feedback on errors)
- Works with any provider through Polyglot

[**→ Instructor Documentation**](/packages/instructor/introduction)

---

### Polyglot

**Use this when you need direct LLM access without structured extraction.**

A unified interface for LLM providers. Write code once, run it against any provider. Useful when you're building chat interfaces, agents, or need raw completions.

```php
<?php
$response = (new LLM)->using('anthropic')->chat("Explain PHP generators");

// Switch providers with one line
$response = (new LLM)->using('openai')->chat("Explain PHP generators");
$response = (new LLM)->using('gemini')->chat("Explain PHP generators");
```

**Why use it:**
- Same code works with 20+ providers
- No vendor lock-in
- Streaming, embeddings, tool calling
- Test with cheap/fast models, deploy with powerful ones

[**→ Polyglot Documentation**](/packages/polyglot/introduction)

---

### HTTP Client

**Use this when you need low-level HTTP control.**

The HTTP layer that powers Polyglot. Most developers never touch this directly, but it's available if you need custom HTTP handling, middleware, or want to build your own LLM integrations.

```php
<?php
$client = new HttpClient();
$response = $client->handle($request);

// Streaming responses
foreach ($client->stream($request) as $chunk) {
    echo $chunk;
}
```

**Why use it:**
- Streaming-first design
- Middleware pipeline
- Multiple backends
- Connection pooling

[**→ HTTP Client Documentation**](/packages/http-client/introduction)

---

### Laravel Integration

**Use this if you're building with Laravel.**

Adds Laravel-specific conveniences: service provider, facades, config publishing, and testing fakes.

```php
<?php
// Use the facade
use Cognesy\Instructor\Facades\Instructor;

$person = Instructor::respond()
    ->withResponseClass(Person::class)
    ->withMessages($text)
    ->get();

// Or inject via dependency injection
public function extract(StructuredOutput $instructor)
{
    return $instructor->withResponseClass(Person::class)->get();
}
```

**Why use it:**
- Auto-discovery (just install and use)
- Laravel-style configuration
- Testing fakes for unit tests
- Integrates with Laravel's logging

[**→ Laravel Documentation**](/packages/laravel/introduction)

---

## Internal Packages

These packages are used internally by Instructor and Polyglot. They're not meant for direct use, but they're available if you're extending the library or curious about the architecture.

| Package | Purpose |
|---------|---------|
| `addons` | Optional extensions (image handling, web scraping, agents) |
| `schema` | PHP class → JSON Schema conversion |
| `messages` | Message/conversation handling |
| `events` | Internal event system |
| `config` | Configuration management |
| `evals` | LLM evaluation tools |
| `metrics` | Usage tracking and observability |
| `templates` | Prompt templating |
| `stream` | Stream processing utilities |

---

## Quick Decision Guide

| I want to... | Use this |
|--------------|----------|
| Extract structured data from text | **Instructor** |
| Extract data from images | **Instructor** |
| Build a chatbot or agent | **Polyglot** |
| Switch between LLM providers easily | **Polyglot** (or Instructor, which includes it) |
| Use Instructor in Laravel | **Instructor** + **Laravel** package |
| Build custom LLM integrations | **HTTP Client** + **Polyglot** |
| Just get started quickly | **Instructor** (includes everything) |

---

## Installation

```bash
# Most developers - get everything
composer require cognesy/instructor-php

# Direct LLM access only (no structured extraction)
composer require cognesy/polyglot

# Laravel integration
composer require cognesy/instructor-laravel

# Low-level HTTP only
composer require cognesy/http-client
```
