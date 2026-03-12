---
title: 'Sandbox: Timeout Guard'
docname: 'sandbox_timeout_guard'
id: 'd204'
---
## Overview

Show policy-based timeout control.
This protects your app from hanging commands.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;

$policy = ExecutionPolicy::in(__DIR__)
    ->withTimeout(1);

$result = Sandbox::host($policy)->execute([
    'php',
    '-r',
    'sleep(3); echo "done\\n";',
]);

echo "Exit: {$result->exitCode()}\n";
echo "Timed out: " . ($result->timedOut() ? 'yes' : 'no') . "\n";
echo "Stdout: " . $result->stdout() . "\n";

assert($result->timedOut() === true, 'Command should have timed out');
?>
```
