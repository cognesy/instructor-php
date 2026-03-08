---
title: Events System
description: 'Learn about the events system in Polyglot.'
---

Polyglot uses an event system to generate internal notifications at the various stages of the execution process.

It has been built primarily to ensure observability of the internal components of the library.

Inference and embeddings listener registration lives on concrete runtimes:

```php
$inferenceRuntime = InferenceRuntime::fromConfig($config)
    ->onEvent(InferenceCompleted::class, $listener)
    ->wiretap($tap);

$embeddingsRuntime = EmbeddingsRuntime::fromConfig($config)
    ->onEvent(EmbeddingsResponseReceived::class, $listener)
    ->wiretap($tap);
```

```php
namespace Cognesy\Events\Dispatchers;

use Cognesy\Events\Event;

class EventDispatcher {
    public function dispatch(Event $event): void { ... }
    public function wiretap(callable $listener): void { ... }
    public function addListener(string $eventClass, callable $listener, int $priority = 0): void { ... }
}

namespace Cognesy\Polyglot\Inference\Events;

class InferenceRequested extends Event {}

class InferenceResponseCreated extends Event {}

class PartialInferenceDeltaCreated extends Event {}

class InferenceStarted extends Event {}

class InferenceCompleted extends Event {}

class InferenceAttemptStarted extends Event {}

class InferenceAttemptSucceeded extends Event {}

class InferenceAttemptFailed extends Event {}
```
