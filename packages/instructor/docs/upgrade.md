---
title: Upgrading Instructor
description: 'Learn how to upgrade your Instructor installation.'
---

Recent changes to the Instructor package may require some manual fixes in your codebase.


## Step 1: Update the package

Run the following command in your CLI:

```bash
composer update cognesy/instructor-php
```

## Step 2: Config files

Correct your config files to use new namespaces.


## Step 3: Instructor config path

Correct INSTRUCTOR_CONFIG_PATHS in .env file to `config/instructor` (or your custom path).


## Step 4: Codebase

Make sure that your code follows new namespaces.

Suggestion: use IDE search and replace to find and replace old namespaces with new ones.

## Step 5: Streaming replay behavior

In 2.0, stream iterators are one-shot by default (`ResponseCachePolicy::None`).

- If your code iterates `partials()`, `responses()` or `sequence()` more than once, it will now throw.
- `finalResponse()` and `finalValue()` are still safe to call repeatedly.
- To enable replay, configure `ResponseCachePolicy::Memory` explicitly.

## Step 6: Mixin inference traits

`HandlesInference` and `HandlesSelfInference` are deprecated in 2.0.

Use `StructuredOutput::using()` with a named preset instead:

```php
<?php
use Cognesy\Instructor\StructuredOutput;

$user = StructuredOutput::using('openai')
    ->with(
        messages: 'Jason is 25 years old and works as an engineer.',
        responseModel: User::class,
    )
    ->getObject();
```

## Step 7: Events 2.0 explicit wiring

If your code previously relied on resolver-style event wiring, migrate to explicit shared bus injection.

Before:

```php
$events = EventBusResolver::using($events);
```

After:

```php
use Cognesy\Events\Dispatchers\EventDispatcher;

$events = $events ?? new EventDispatcher(name: 'instructor.runtime');
```

Pass the same `$events` instance into related runtimes/builders (for example `HttpClientBuilder`, `InferenceRuntime`, `StructuredOutputRuntime`) so listeners and wiretaps observe the full flow.

For full details, see: `packages/events/MIGRATION-2.0.md`.
