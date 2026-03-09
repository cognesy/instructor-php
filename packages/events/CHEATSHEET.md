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
```

Notes:
- `addListener('*', $listener)` also registers a global listener.
- Class listeners run first (priority desc), wiretaps run after.
- Parent classes and implemented interfaces are considered for listener matching.

## Event Base Class (`Event`)

```php
use Cognesy\Events\Event;
use Psr\Log\LogLevel;

$event = new Event(['status' => 'ok']);

$id = $event->id;
$createdAt = $event->createdAt;
$data = $event->data;
$event->logLevel = LogLevel::INFO;

$name = $event->name();
$asLog = $event->asLog();
$asConsole = $event->asConsole();
$array = $event->toArray();

$event->print(threshold: LogLevel::DEBUG);
$event->printLog();
$event->printDebug();
```

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
$symfonyBridge->wiretap(fn(object $e) => null);
$symfonyBridge->dispatch(new MyEvent());
```

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

$service
    ->withEventHandler($events)
    ->onEvent(Event::class, fn(object $e) => null)
    ->wiretap(fn(object $e) => null);
```

## Event Formatter Utilities

```php
use Cognesy\Events\Utils\EventFormatter;

$shortName = EventFormatter::toShortName($event);
$fullName = EventFormatter::toFullName($event);

$line = EventFormatter::logFormat($event, 'message');
$console = EventFormatter::consoleFormat($event, 'message', quote: false);

$shouldPrint = EventFormatter::logFilter('info', 'warning');
$rank = EventFormatter::logLevelRank('error');
```

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

$wiretap = $printer->wiretap($formatter);
$wiretap(new stdClass());
```
