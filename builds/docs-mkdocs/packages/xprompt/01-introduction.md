---
title: Introduction
description: 'Prompts as PHP classes — composable, testable, swappable'
---

# Introduction

Xprompt turns prompts into ordinary PHP classes. Instead of scattering prompt strings across your codebase, you write a class that returns its content from a `body()` method. Because every prompt implements `Stringable`, it plugs directly into Polyglot, Instructor, and Agents without adapters or glue code.

```php
use Cognesy\Xprompt\Prompt;

class Persona extends Prompt
{
    public function body(mixed ...$ctx): string
    {
        return "You are a {$ctx['role']} expert.";
    }
}

echo Persona::with(role: 'security');
// "You are a security expert."
// @doctest id="ddd1"
```

## Why Classes?

Plain strings work until they don't. As prompts grow, you need variables, conditionals, reusable sections, and the ability to swap one version for another without touching calling code. Xprompt gives you these things using the tools you already know — classes, composition, and templates — with no framework overhead.

A prompt class can:

- **Return a string** — inline text with interpolated context
- **Return an array** — compose multiple prompts, strings, and nulls into a single output
- **Use a Twig template** — separate markup from logic, add front matter metadata
- **Be swapped at runtime** — register variants and override by name via the registry

## How It Fits

Xprompt is a leaf package with no opinion about how you call an LLM. Every prompt renders to a string, so it works anywhere a string works:

```php
// StructuredOutput (accepts Stringable for system prompt)
StructuredOutput::with(
    system: Persona::with(role: 'analyst'),
    responseModel: MyModel::class,
)->get();

// Agents (via AgentContext)
$context->withSystemPrompt(ReviewSystem::with(content: $doc));
// @doctest id="870e"
```

## What You'll Learn

1. **[Getting Started](02-getting-started.md)** — Your first prompt class, context, and rendering
2. **[Composition](03-composition.md)** — Building complex prompts from smaller pieces
3. **[Templates](04-templates.md)** — Twig-backed prompts with front matter
4. **[Structured Data](05-structured-data.md)** — NodeSet for criteria, rubrics, and taxonomies
5. **[Variants & Registry](06-variants-and-registry.md)** — Swapping prompt implementations without changing calling code
6. **[Configuration](07-configuration.md)** — Template engine config and preset loading
