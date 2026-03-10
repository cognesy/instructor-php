---
title: 'Sandbox: Host ls -Al'
docname: 'sandbox_host_ls'
id: 'd201'
---
## Overview

Run a simple command through `Sandbox::host()`.
This is the quickest way to verify sandbox execution in your environment.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;

$policy = ExecutionPolicy::in(__DIR__)
    ->withTimeout(3);

$result = Sandbox::host($policy)->execute(['ls', '-Al']);

echo "Exit: {$result->exitCode()}\n";
echo "--- stdout ---\n";
echo $result->stdout() . "\n";

if ($result->stderr() !== '') {
    echo "--- stderr ---\n";
    echo $result->stderr() . "\n";
}

assert($result->exitCode() === 0, 'ls command should exit with code 0');
assert(!empty($result->stdout()), 'ls command should produce output');
?>
```
