# Events Package

Small PSR-14 compatible event layer for InstructorPHP.

Use it to dispatch domain events, register typed listeners, and add global wiretaps for observability.

## Example

```php
<?php

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Events\Event;

final class UserLoggedIn extends Event {}

$events = new EventDispatcher();

$events->addListener(UserLoggedIn::class, function (UserLoggedIn $event): void {
    // handle typed event
});

$events->wiretap(function (object $event): void {
    // observe every event
});

$events->dispatch(new UserLoggedIn(['userId' => 123]));
```

## Documentation

- `packages/events/CHEATSHEET.md`
- `packages/events/MIGRATION-2.0.md`
- `packages/events/tests/`
