---
title: Session Management
description: 'Continue the latest session or resume a specific session ID.'
---

All bridges support continuing and resuming sessions.

## Continue Latest Session

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()
    ->continueSession()
    ->execute('Continue from the previous plan and apply step 2.');
```

## Resume Specific Session

```php
use Cognesy\AgentCtrl\AgentCtrl;

$first = AgentCtrl::codex()->execute('Create a migration plan.');
$sessionId = $first->sessionId();

if ($sessionId !== null) {
    $next = AgentCtrl::codex()
        ->resumeSession((string) $sessionId)
        ->execute('Now implement the first migration.');
}
```

`sessionId()` returns `AgentSessionId|null`.
