---
title: 'Sandbox: Run PHP Script'
docname: 'sandbox_run_php_script'
id: 'd202'
---
## Overview

Run inline PHP in sandboxed execution.
Useful when tools need controlled script execution with predictable policy limits.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Sandbox\Sandbox;

$policy = ExecutionPolicy::in(__DIR__)
    ->withTimeout(5);

$sandbox = Sandbox::with($policy)->using(SandboxDriver::Host);

$result = $sandbox->execute([
    'php',
    '-r',
    'echo "sandbox says hi\\n"; echo "cwd=" . getcwd() . "\\n";',
]);

echo $result->stdout();
?>
```
