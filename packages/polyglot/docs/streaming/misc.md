---
title: Stream Processing Helpers
description: Transform, filter, and aggregate streaming deltas with built-in helpers.
---

Beyond simple iteration, `InferenceStream` provides a set of functional helpers for processing deltas. These methods build on top of the `deltas()` generator, so each one consumes the stream -- you should use only one of them per stream instance.


## Reducing to a Single Value

The `reduce()` method works like `array_reduce`: it folds every delta into an accumulator and returns the final value. This is useful when you need a single result derived from the entire stream:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$text = Inference::using('openai')
    ->withMessages(Messages::fromString('Write three short lines about queues.'))
    ->stream()
    ->reduce(
        fn(string $carry, $delta) => $carry . $delta->contentDelta,
        '',
    );

echo $text;
```

Because `reduce()` drains the entire stream before returning, it blocks until the response is complete.


## Mapping Deltas

The `map()` method transforms each delta into a new value and yields the results as a generator. Use it to extract or reshape data from each chunk without consuming the stream eagerly:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('List five fun facts about PHP.'))
    ->stream();

foreach ($stream->map(fn($delta) => strtoupper($delta->contentDelta)) as $chunk) {
    echo $chunk;
}
```


## Filtering Deltas

The `filter()` method yields only the deltas that satisfy a given predicate. Deltas for which the callback returns `false` are silently skipped:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Count from one to ten.'))
    ->stream();

// Only process deltas that contain digits
foreach ($stream->filter(fn($delta) => preg_match('/\d/', $delta->contentDelta)) as $delta) {
    echo $delta->contentDelta;
}
```


## Collecting All Deltas

The `all()` method drains the stream and returns every visible delta as an array. This is handy for inspection or testing, but keep in mind that it loads the entire stream into memory:

```php
<?php

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Inference;

$deltas = Inference::using('openai')
    ->withMessages(Messages::fromString('Say hello.'))
    ->stream()
    ->all();

echo "Received " . count($deltas) . " deltas.\n";
```


## Accessing the Last Delta

After the stream has been consumed (either partially or fully), you can retrieve the most recently yielded delta with `lastDelta()`:

```php
$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('What is 2 + 2?'))
    ->stream();

foreach ($stream->deltas() as $delta) {
    // process...
}

$last = $stream->lastDelta();
echo $last->finishReason; // e.g. "stop"
```

This is particularly useful for inspecting the finish reason or final usage data without keeping track of it manually during iteration.


## Token Usage

The `usage()` method returns the accumulated `Usage` object for the stream, containing input tokens, output tokens, and any cache or reasoning token counts reported by the provider:

```php
$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Summarize the theory of relativity.'))
    ->stream();

foreach ($stream->deltas() as $delta) {
    echo $delta->contentDelta;
}

$usage = $stream->usage();
echo "\nTokens used: input={$usage->inputTokens}, output={$usage->outputTokens}\n";
```


## Execution Metadata

The `execution()` method returns the underlying `InferenceExecution` object, which contains the original request, the finalized response (once the stream completes), and execution metadata such as the execution ID:

```php
$stream = Inference::using('openai')
    ->withMessages(Messages::fromString('Hello!'))
    ->stream();

$stream->final(); // ensure stream is consumed

$execution = $stream->execution();
echo "Execution ID: " . $execution->id->toString() . "\n";
echo "Model used: " . $execution->request()->model() . "\n";
```


## Summary of Available Methods

| Method | Returns | Consumes stream? | Description |
|---|---|---|---|
| `deltas()` | `Generator<PartialInferenceDelta>` | Yes | Yields visible deltas one by one. |
| `map(callable)` | `iterable<T>` | Yes | Transforms each delta via a callback. |
| `filter(callable)` | `iterable<PartialInferenceDelta>` | Yes | Yields only deltas matching a predicate. |
| `reduce(callable, initial)` | `mixed` | Yes (blocking) | Folds all deltas into a single value. |
| `all()` | `array<PartialInferenceDelta>` | Yes (blocking) | Collects all deltas into an array. |
| `onDelta(callable)` | `self` | No (registers callback) | Registers a callback fired for each visible delta. |
| `final()` | `?InferenceResponse` | Drains if needed | Returns the assembled final response. |
| `lastDelta()` | `?PartialInferenceDelta` | No | Returns the most recently yielded delta. |
| `usage()` | `Usage` | No | Returns accumulated token usage. |
| `execution()` | `InferenceExecution` | No | Returns the execution context and metadata. |
