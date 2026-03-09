---
title: Session Management
description: 'Continue the latest session or resume a specific session.'
---

All supported bridges can keep working within an existing session.

## Continue the Latest Session

```php
use Cognesy\AgentCtrl\AgentCtrl;

$response = AgentCtrl::codex()
    ->continueSession()
    ->execute('Continue from the previous task.');
```

## Resume a Specific Session

```php
use Cognesy\AgentCtrl\AgentCtrl;

$first = AgentCtrl::claudeCode()->execute('Create a short plan.');
$sessionId = $first->sessionId();

if ($sessionId !== null) {
    $next = AgentCtrl::claudeCode()
        ->resumeSession((string) $sessionId)
        ->execute('Now apply the first step.');
}
```

## Reading the Session ID

`AgentResponse::sessionId()` returns `AgentSessionId|null`.

Keep that value if you want to continue the same conversation later.
