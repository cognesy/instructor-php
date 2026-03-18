---
title: Composition
description: 'Build complex system prompts from smaller, reusable prompt classes'
---

# Composition

The real power of xprompt is composition. A `body()` method can return an **array** of renderables — strings, other prompts, or nulls. The framework recursively renders each element and joins the results with double newlines.

## Basic Composition

```php
class Persona extends Prompt
{
    public function body(mixed ...$ctx): string
    {
        return "You are a {$ctx['role']} expert.";
    }
}

class Task extends Prompt
{
    public function body(mixed ...$ctx): string
    {
        return "Analyze the following document for {$ctx['focus']}.";
    }
}

class ReviewSystem extends Prompt
{
    public function body(mixed ...$ctx): array
    {
        return [
            Persona::with(role: 'code review'),
            Task::with(focus: 'security vulnerabilities'),
            "## Document\n\n" . $ctx['content'],
        ];
    }
}

echo ReviewSystem::with(content: $code);
// @doctest id="8fa0"
```

Output:

```
You are a code review expert.

Analyze the following document for security vulnerabilities.

## Document

<content here>
// @doctest id="438d"
```

Array elements are joined with `"\n\n"`. Empty strings and nulls are silently skipped.

## Conditional Sections

Return `null` to exclude a section. This keeps conditionals clean:

```php
class SystemPrompt extends Prompt
{
    public function body(mixed ...$ctx): array
    {
        return [
            Persona::with(role: $ctx['role']),
            Guidelines::make(),
            ($ctx['strict'] ?? false) ? Constraints::make() : null,
            "## Input\n\n" . $ctx['input'],
        ];
    }
}
// @doctest id="7106"
```

When `strict` is false, the `Constraints` section is simply absent from the output — no empty lines, no placeholders.

## Context Propagation

When a parent prompt renders a child via composition, the parent's context automatically flows down. Children receive the merged context:

```php
class Parent extends Prompt
{
    public function body(mixed ...$ctx): array
    {
        return [
            Child::make(), // receives parent's $ctx
        ];
    }
}

// Child sees lang: 'en' even though it wasn't explicitly passed
echo Parent::with(lang: 'en');
// @doctest id="d166"
```

Children that bind their own context via `with()` merge it with the parent's context — the child's bindings take precedence for shared keys.

## Nesting

Composition nests arbitrarily. A prompt can return an array containing prompts that themselves return arrays:

```php
class TopLevel extends Prompt
{
    public function body(mixed ...$ctx): array
    {
        return [
            SectionA::make(),  // may return string or array
            SectionB::make(),  // may return string or array
        ];
    }
}
// @doctest id="acaa"
```

The `flatten()` function handles all the recursion. It traverses nested arrays, renders any `Prompt` or `Stringable` objects it finds, filters out nulls and empty strings, and joins everything with `"\n\n"`.

## Mixing Inline and Template-Backed Prompts

Composition doesn't care how each piece generates its content. You can freely mix inline prompts, template-backed prompts, and raw strings in the same array:

```php
class FullSystem extends Prompt
{
    public function body(mixed ...$ctx): array
    {
        return [
            Persona::make(),                  // inline body()
            ScoringRubric::make(),            // Twig template
            "Be concise in your response.",   // plain string
        ];
    }
}
// @doctest id="fa24"
```

## Next Steps

- [Templates](04-templates.md) — use Twig files for content that's easier to maintain as markup
- [Structured Data](05-structured-data.md) — render lists, criteria, and taxonomies with NodeSet
