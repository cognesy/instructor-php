---
title: 'Sandbox API: Firejail'
docname: 'sandbox_api_firejail_echo'
id: 'd213'
tags:
  - 'sandbox-apis'
  - 'firejail'
  - 'drivers'
---
## Overview

Minimal firejail driver API check.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;

$policy = ExecutionPolicy::in(__DIR__)
    ->withTimeout(10);

try {
    $result = Sandbox::firejail($policy)
        ->execute(['sh', '-lc', 'echo "hello from firejail sandbox"']);

    assert($result->exitCode() === 0, 'Firejail echo should exit with code 0');
    echo "Exit: {$result->exitCode()}\n";
    echo $result->stdout();
} catch (Throwable $e) {
    echo "Firejail sandbox unavailable: {$e->getMessage()}\n";
}
?>
```
