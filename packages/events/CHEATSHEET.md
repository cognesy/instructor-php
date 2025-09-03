# Events Package - Deep Reference

## Core Architecture

### Event System Contracts
```php
interface CanHandleEvents extends EventDispatcherInterface, ListenerProviderInterface {
    public function addListener(string $name, callable $listener, int $priority = 0): void;
    public function wiretap(callable $listener): void;
}

// PSR-14 compliance: EventDispatcherInterface, ListenerProviderInterface
// Custom extensions: wiretap for global event observation
```

### Event Base Class
```php
class Event implements JsonSerializable {
    public readonly string $id;              // UUID v4
    public readonly DateTimeImmutable $createdAt;
    public mixed $data;                      // Event payload
    public $logLevel = LogLevel::DEBUG;      // PSR-3 log level
}
```

## Event Definition and Usage

### Basic Event Creation
```php
// Simple event with data
$event = new Event(['user_id' => 123, 'action' => 'login']);

// Event properties are auto-generated
$event->id;        // UUID v4: "a1b2c3d4-..."  
$event->createdAt; // DateTimeImmutable
$event->data;      // ['user_id' => 123, 'action' => 'login']

// Data transformation logic
match(true) {
    is_array($data) => $data,
    is_object($data) => get_object_vars($data),
    default => $data,
}
```

### Custom Event Classes
```php
class UserLoggedIn extends Event {
    public $logLevel = LogLevel::INFO;
    
    public function __construct(int $userId, string $sessionId) {
        parent::__construct([
            'user_id' => $userId,
            'session_id' => $sessionId,
            'timestamp' => time()
        ]);
    }
}
```

### Event Introspection
```php
$event->name();        // Short class name (ReflectionClass::getShortName)
$event->toArray();     // Event as array (json_decode/encode hack)
$event->jsonSerialize(); // get_object_vars($this)
$event->__toString();  // JSON encoded data
```

## Event Bus Resolution System

### EventBusResolver Factory
```php
// Default event dispatcher
$events = EventBusResolver::default();

// Using existing dispatcher
$events = EventBusResolver::using($customDispatcher);
$events = EventBusResolver::using(null); // creates default EventDispatcher

// Resolution logic prevents double-wrapping
match(true) {
    is_null($eventHandler) => new EventDispatcher(),
    $eventHandler instanceof EventBusResolver => $eventHandler->eventHandler, // unwrap
    $eventHandler instanceof EventDispatcher => $eventHandler, // direct use
    $eventHandler instanceof CanHandleEvents => $eventHandler, // interface match
    $eventHandler instanceof EventDispatcherInterface => new EventDispatcher(parent: $eventHandler), // wrap
    default => $eventHandler,
}
```

### Event Bus API
```php
$events = EventBusResolver::default();

// Add class-specific listeners
$events->addListener(UserLoggedIn::class, function($event) { /* handle */ });

// Add global listeners (wiretaps)
$events->wiretap(function($event) { /* observe all events */ });

// Dispatch events
$result = $events->dispatch($userLoggedInEvent);

// Get listeners for introspection
$listeners = $events->getListenersForEvent($event);
```

## Event Dispatcher Implementation

### Core EventDispatcher
```php
class EventDispatcher implements CanHandleEvents {
    private string $name = 'default';
    private ?EventDispatcherInterface $parent;
    /** @var array<string, SplPriorityQueue> */
    private array $listeners = [];
}
```

### Listener Management
```php
// Priority-based listener registration
$dispatcher->addListener(UserLoggedIn::class, $callback, $priority = 0);

// Higher priority = executed first
$dispatcher->addListener(UserLoggedIn::class, $highPriority, 100);
$dispatcher->addListener(UserLoggedIn::class, $lowPriority, -100);

// Wiretaps stored as '*' listeners
$dispatcher->wiretap($globalListener); // internally calls addListener('*', $listener)
```

### Event Dispatch Flow
```php
public function dispatch(object $event): object {
    // 1. Class-specific listeners (honour StoppableEventInterface)
    foreach ($this->classListeners($event) as $listener) {
        $listener($event);
        if ($event instanceof StoppableEventInterface && $event->isPropagationStopped()) {
            break; // stop propagation
        }
    }
    
    // 2. Wiretaps - always run (no propagation stopping)
    if (isset($this->listeners['*'])) {
        foreach (clone $this->listeners['*'] as $tap) {
            $tap($event);
        }
    }
    
    // 3. Bubble up to parent dispatcher
    $this->parent?->dispatch($event);
    
    return $event;
}
```

### Hierarchical Type Matching
```php
private function classListeners(object $event): iterable {
    // Match event class, parent classes, and interfaces
    $types = array_merge(
        [get_class($event)],           // Exact class match
        class_parents($event),         // Inheritance chain
        class_implements($event)       // Interface implementations
    );
    
    foreach ($types as $type) {
        if (isset($this->listeners[$type])) {
            foreach (clone $this->listeners[$type] as $listener) {
                yield $listener;
            }
        }
    }
}
```

## Framework Integrations

### Laravel Event Dispatcher Bridge
```php
class LaravelEventDispatcher implements CanHandleEvents {
    private Dispatcher $dispatcher;           // Laravel's event dispatcher
    private array $registry = [];            // Local listener registry
    private SplPriorityQueue $taps;          // Wiretap listeners
}

// Dual registration pattern
public function addListener(string $name, callable $listener, int $priority = 0): void {
    if ($name === '*') {
        $this->taps->insert($listener, $priority);
        return;
    }
    
    // Store locally for introspection
    $queue = $this->registry[$name] ??= new SplPriorityQueue();
    $queue->insert($listener, $priority);
    
    // Register with Laravel (priority ignored, order preserved)
    $this->dispatcher->listen($name, $listener);
}

// Dispatch flow: Laravel first, then taps
public function dispatch(object $event): object {
    $this->dispatcher->dispatch($event); // Laravel handles framework/user listeners
    
    foreach (clone $this->taps as $tap) { // Run taps after Laravel
        $tap($event);
    }
    return $event;
}
```

### Symfony Event Dispatcher Bridge
```php
class SymfonyEventDispatcher implements CanHandleEvents {
    private EventDispatcherInterface $dispatcher; // Symfony dispatcher
    private SplPriorityQueue $taps;               // Wiretap queue
}

// Delegation pattern with wiretap extension
public function addListener(string $name, callable $listener, int $priority = 0): void {
    if ($name === '*') {
        $this->taps->insert($listener, $priority);
        return;
    }
    $this->dispatcher->addListener($name, $listener, $priority);
}

// Framework first, taps after
public function dispatch(object $event): object {
    $event = $this->dispatcher->dispatch($event); // Symfony listeners first
    
    foreach (clone $this->taps as $tap) {         // Taps always run
        $tap($event);
    }
    return $event;
}
```

## HandlesEvents Trait

### Event Handler Integration
```php
trait HandlesEvents {
    protected CanHandleEvents $events;
    
    public function withEventHandler(CanHandleEvents|EventDispatcherInterface $events): static {
        $this->events = EventBusResolver::using($events);
        return $this;
    }
    
    public function dispatch(Event $event): object {
        return $this->events->dispatch($event);
    }
    
    public function wiretap(?callable $listener): self {
        if ($listener !== null) {
            $this->events->wiretap($listener);
        }
        return $this;
    }
    
    public function onEvent(string $class, ?callable $listener): self {
        if ($listener !== null) {
            $this->events->addListener($class, $listener);
        }
        return $this;
    }
}
```

### Usage in Classes
```php
class UserService {
    use HandlesEvents;
    
    public function login(User $user) {
        // Business logic...
        
        $this->dispatch(new UserLoggedIn($user->id, session_id()));
        
        return $user;
    }
}

// Setup with fluent API
$service = (new UserService())
    ->withEventHandler($eventDispatcher)
    ->wiretap(fn($event) => logger()->info($event->asLog()))
    ->onEvent(UserLoggedIn::class, fn($event) => $this->sendWelcomeEmail($event));
```

## Event Formatting and Logging

### Event Output Formatting
```php
// Console output with colored formatting
$event->asConsole($quote = false); 
// Format: "(.uuid) HH:mm:ss vms  LEVEL        EventName - message"

$event->print($quote = false, $threshold = LogLevel::DEBUG);
$event->printLog();     // Raw log format
$event->printDebug();   // Console + dump()

// Log file format
$event->asLog();
// Format: "(uuid) YYYY-mm-dd HH:mm:ss vms (LEVEL) [Full\Class\Name] - message"
```

### EventFormatter Utilities
```php
EventFormatter::toShortName($event);  // Class basename
EventFormatter::toFullName($event);   // Fully qualified class name

// Log level filtering
EventFormatter::logFilter($eventLevel, $threshold); // bool
EventFormatter::logLevelRank($level); // int (0=EMERGENCY to 7=DEBUG)

// Log level hierarchy (lower number = higher severity)
LogLevel::EMERGENCY => 0,  LogLevel::ALERT => 1,     LogLevel::CRITICAL => 2,
LogLevel::ERROR => 3,      LogLevel::WARNING => 4,   LogLevel::NOTICE => 5,
LogLevel::INFO => 6,       LogLevel::DEBUG => 7
```


## Advanced Patterns

### Event Propagation Control
```php
// StoppableEventInterface support
class CancellableEvent extends Event implements StoppableEventInterface {
    private bool $stopped = false;
    
    public function stopPropagation(): void {
        $this->stopped = true;
    }
    
    public function isPropagationStopped(): bool {
        return $this->stopped;
    }
}

// Dispatcher honours stopPropagation for class listeners, not wiretaps
```

### Priority-Based Execution
```php
// SplPriorityQueue manages execution order
$dispatcher->addListener(EventClass::class, $criticalHandler, 1000);   // First
$dispatcher->addListener(EventClass::class, $normalHandler, 0);       // Middle  
$dispatcher->addListener(EventClass::class, $cleanupHandler, -1000);  // Last

// Cloning prevents iterator corruption during dispatch
foreach (clone $this->listeners[$type] as $listener) {
    yield $listener;
}
```

### Parent Dispatcher Hierarchy
```php
$parent = new EventDispatcher('parent');
$child = new EventDispatcher('child', $parent);

// Events bubble up: child listeners first, then parent listeners
$child->dispatch($event);
// 1. Child class listeners (with stopPropagation)
// 2. Child wiretaps (always run)  
// 3. Parent->dispatch($event) recursively
```

### Introspection and Debugging
```php
$dispatcher->name();                    // Dispatcher name
$dispatcher->getListenersForEvent($event); // All applicable listeners
$dispatcher->dispatcher();              // Parent dispatcher or self

// Event debugging
$event->printDebug(); // Console output + var_dump
$event->toArray();    // Full event state as array
```