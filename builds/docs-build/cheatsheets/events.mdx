---
title: Events
description: PSR-compatible event system — dispatching, listeners, wiretaps, and event handling
package: events
---

# Events Package Cheatsheet

Code-verified reference for `packages/events`.

## Core Interface

```php
use Cognesy\Events\Contracts\CanHandleEvents;

// Extends PSR EventDispatcherInterface + ListenerProviderInterface
interface CanHandleEvents {
    public function addListener(string $name, callable $listener, int $priority = 0): void;
    public function wiretap(callable $listener): void;
    public function dispatch(object $event): object;
    public function getListenersForEvent(object $event): iterable;
}
```

### `CanAcceptEventHandler`

```php
use Cognesy\Events\Contracts\CanAcceptEventHandler;

interface CanAcceptEventHandler {
    public function withEventHandler(CanHandleEvents $events): static;
}
```

### `CanFormatConsoleEvent`

```php
use Cognesy\Events\Contracts\CanFormatConsoleEvent;
use Cognesy\Events\Data\ConsoleEventLine;

interface CanFormatConsoleEvent {
    public function format(object $event): ?ConsoleEventLine;
}
```

## Basic Dispatcher Usage

```php
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Event;

final class UserLoggedIn extends Event {}

$events = new EventDispatcher(name: 'app');

$events->addListener(UserLoggedIn::class, function (UserLoggedIn $event): void {
    // handle event
}, priority: 100);

$events->wiretap(function (object $event): void {
    // observe all events
});

$events->dispatch(new UserLoggedIn(['userId' => 123]));

// Additional public methods on EventDispatcher:
$events->name();        // returns the dispatcher name (string)
$events->dispatcher();  // returns the parent dispatcher or self (EventDispatcherInterface)
```

Constructor: `new EventDispatcher(string $name = 'default', ?EventDispatcherInterface $parent = null)`

Notes:
- `addListener('*', $listener)` also registers a global listener.
- Class listeners run first (priority desc), wiretaps run after.
- Parent classes and implemented interfaces are considered for listener matching.
- When a parent dispatcher is set, events bubble up to it after local dispatch.

## Event Base Class (`Event`)

```php
use Cognesy\Events\Event;
use Psr\Log\LogLevel;

$event = new Event(['status' => 'ok']);

$id = $event->id;               // readonly string (UUID)
$createdAt = $event->createdAt; // readonly DateTimeImmutable
$data = $event->data;           // mixed
$event->logLevel;               // default: LogLevel::DEBUG

$name = $event->name();                         // short class name
$asLog = $event->asLog();                       // log-formatted string
$asConsole = $event->asConsole(quote: false);   // console-formatted string
$array = $event->toArray();                     // delegates to jsonSerialize()
$json = $event->jsonSerialize();                // array<string, mixed> (implements JsonSerializable)

$event->print(quote: false, threshold: LogLevel::DEBUG);
$event->printLog();
$event->printDebug();
```

Notes:
- `Event` implements `JsonSerializable`.
- Object payloads are normalized to a simplified public-property array via `get_object_vars()`.
- `__toString()` returns best-effort JSON of the event data.

## Explicit Wiring

```php
use Cognesy\Events\Dispatchers\EventDispatcher;

$events = new EventDispatcher(name: 'app');
```

Pass `CanHandleEvents` explicitly to classes that emit events.

## Framework Bridges

### Symfony Bridge

```php
use Cognesy\Events\Dispatchers\SymfonyEventDispatcher;
use Cognesy\Events\Event;

final class MyEvent extends Event {}

$symfonyBridge = new SymfonyEventDispatcher($symfonyDispatcher);
$symfonyBridge->addListener(MyEvent::class, fn(object $e) => null, priority: 50);
$symfonyBridge->wiretap(fn(object $e) => null, priority: 0);
$symfonyBridge->dispatch(new MyEvent());
```

Note: `SymfonyEventDispatcher::wiretap()` accepts an optional `int $priority = 0` parameter (beyond the interface contract).

## `HandlesEvents` Trait

```php
use Cognesy\Events\Event;
use Cognesy\Events\Traits\HandlesEvents;

final class Service {
    use HandlesEvents;

    public function run(): void {
        $this->dispatch(new Event(['action' => 'run']));
    }
}

$service = new Service();
$service
    ->withEventHandler($events)                    // returns static
    ->onEvent(Event::class, fn(object $e) => null) // returns self
    ->wiretap(fn(object $e) => null);              // returns self
```

Methods:
- `withEventHandler(CanHandleEvents $events): static`
- `dispatch(Event $event): object`
- `onEvent(string $class, ?callable $listener): self` — null listener is a no-op
- `wiretap(?callable $listener): self` — null listener is a no-op

Call `withEventHandler($events)` before `dispatch()`, `onEvent()`, or `wiretap()` — throws `LogicException` otherwise.

## Event Formatter Utilities

```php
use Cognesy\Events\Utils\EventFormatter;

$shortName = EventFormatter::toShortName($event); // e.g. "UserLoggedIn"
$fullName = EventFormatter::toFullName($event);   // e.g. "App\\Events\\UserLoggedIn"

$line = EventFormatter::logFormat($event, 'message');
$console = EventFormatter::consoleFormat($event, 'message', quote: false);

$shouldPrint = EventFormatter::logFilter('info', 'warning'); // bool
$rank = EventFormatter::logLevelRank('error');               // int (0=emergency .. 7=debug)
```

Notes:
- `logFilter($threshold, $eventLevel)` returns `true` when `$eventLevel` severity is equal to or higher than `$threshold`.
- Log level ranks: emergency=0, alert=1, critical=2, error=3, warning=4, notice=5, info=6, debug=7, other=8.

## Console Event Printer

```php
use Cognesy\Events\Contracts\CanFormatConsoleEvent;
use Cognesy\Events\Data\ConsoleEventLine;
use Cognesy\Events\Enums\ConsoleColor;
use Cognesy\Events\Support\ConsoleEventPrinter;

$printer = new ConsoleEventPrinter(useColors: true, showTimestamps: true);

$formatter = new class implements CanFormatConsoleEvent {
    public function format(object $event): ?ConsoleEventLine {
        return new ConsoleEventLine('INFO', 'event received', ConsoleColor::Green, 'events');
    }
};

// Create a wiretap closure from a formatter
$wiretap = $printer->wiretap($formatter); // returns Closure(object): void
$wiretap(new stdClass());

// Or print a line directly
$printer->printIfAny($line); // accepts ?ConsoleEventLine, no-op on null
```

### `ConsoleEventLine`

```php
new ConsoleEventLine(
    label: 'INFO',
    message: 'something happened',
    color: ConsoleColor::Green,   // default: ConsoleColor::Default
    context: 'my-module',         // default: null
);
```

### `ConsoleColor` Enum

```php
use Cognesy\Events\Enums\ConsoleColor;

// Cases: Default, Red, Green, Yellow, Blue, Magenta, Cyan, Dark
$ansi = ConsoleColor::Green->ansiCode(); // returns ANSI code string, e.g. "32"
```
