---
title: 'Getting Started'
description: 'Create your first prompt class, pass context, and render output'
---

# Getting Started

## Your First Prompt

Extend `Prompt` and implement `body()`. That's it.

```php
use Cognesy\Xprompt\Prompt;

class Greeting extends Prompt
{
    public function body(mixed ...$ctx): string
    {
        return "Hello, {$ctx['name']}!";
    }
}
// @doctest id="dc75"
```

Render it:

```php
echo Greeting::with(name: 'Alice');
// "Hello, Alice!"
// @doctest id="b3e2"
```

## Creating Instances

There are two static constructors:

```php
// Bare instance — context passed at render time
$prompt = Greeting::make();
echo $prompt->render(name: 'Bob');

// Pre-bound context — stored and merged at render time
$prompt = Greeting::with(name: 'Charlie');
echo $prompt->render(); // "Hello, Charlie!"
// @doctest id="37cc"
```

## Passing Context

Context is passed as named arguments. Pre-bound context from `with()` merges with context passed to `render()`, with render-time values taking precedence:

```php
class Welcome extends Prompt
{
    public function body(mixed ...$ctx): string
    {
        return "{$ctx['greeting']}, {$ctx['name']}!";
    }
}

$prompt = Welcome::with(greeting: 'Hi');
echo $prompt->render(name: 'Dana');            // "Hi, Dana!"
echo $prompt->render(name: 'Eve', greeting: 'Hey'); // "Hey, Eve!"
// @doctest id="1212"
```

## Stringable

Every prompt implements `Stringable`, so you can use it anywhere PHP accepts a string:

```php
$persona = Persona::with(role: 'analyst');

// String concatenation
$full = "System: " . $persona;

// String interpolation
echo "Using prompt: {$persona}";

// Pass to any API accepting string|Stringable
$inference->withSystem($persona)->create();
// @doctest id="a358"
```

## Returning Null

A `body()` that returns `null` renders as an empty string. This is intentional — it enables conditional composition, which is covered in [Composition](03-composition.md).

```php
class MaybeDisclaimer extends Prompt
{
    public function body(mixed ...$ctx): string|array|null
    {
        return ($ctx['strict'] ?? false)
            ? 'Follow instructions exactly. Do not improvise.'
            : null;
    }
}
// @doctest id="6641"
```

## Next Steps

- [Composition](03-composition.md) — combine prompts into larger structures
- [Templates](04-templates.md) — use Twig files for complex prompt content
