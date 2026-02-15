# Peer Review: Event Wiring Fix

Implement this as a builder-level event hub with late normalization. That makes call order irrelevant.

## 1. Keep one stable internal event bus in `AgentBuilder`

Use a proxy parent instead of replacing the dispatcher object.

```php
final class ParentEventProxy implements EventDispatcherInterface {
    private ?EventDispatcherInterface $parent = null;

    public function setParent(EventDispatcherInterface $parent): void {
        $this->parent = $parent;
    }

    public function dispatch(object $event): object {
        if ($this->parent === null) {
            return $event;
        }
        return $this->parent->dispatch($event);
    }
}
```

```php
// AgentBuilder fields
private EventDispatcher $events;
private ParentEventProxy $parentProxy;

// __construct
$this->parentProxy = new ParentEventProxy();
$this->events = new EventDispatcher('agent-builder', $this->parentProxy);

// withEvents
public function withEvents(CanHandleEvents $events): self {
    $this->parentProxy->setParent($events);
    return $this;
}
```

This preserves all listeners already registered on `$this->events`, regardless of when `withEvents()` is called.

## 2. Normalize driver wiring in `build()`

Always rebind driver to final events/compiler at build time.

```php
private function resolveDriver(CanCompileMessages $compiler): CanUseTools {
    $driver = $this->driver ?? new ToolCallingDriver(llm: LLMProvider::new());

    if ($driver instanceof CanAcceptEventHandler) {
        $driver = $driver->withEventHandler($this->events);
    }

    if ($driver instanceof CanAcceptMessageCompiler) {
        $driver = $driver->withMessageCompiler($compiler);
    }

    return $driver;
}
```

## 3. Update `UseLlmConfig`

Do not rely on install-time event wiring. It can build a driver, but `build()` is the source of truth for final rebinding.

## 4. Add tests that enforce commutativity

1. `withEvents()->withCapability(new UseLlmConfig())` equals `withCapability(new UseLlmConfig())->withEvents()`.
2. Capability-added listeners still fire if `withEvents()` is called later.
3. Latest `withEvents()` parent receives bubbled events.

This removes the “call X before Y” contract while keeping API simple.

