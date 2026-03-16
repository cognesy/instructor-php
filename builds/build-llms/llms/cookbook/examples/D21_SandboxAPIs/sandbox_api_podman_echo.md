---
title: 'Sandbox API: Podman'
docname: 'sandbox_api_podman_echo'
id: 'd212'
---
## Overview

Minimal podman driver API check.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;

$policy = ExecutionPolicy::in(__DIR__)
    ->withTimeout(10);

try {
    $result = Sandbox::podman($policy, image: 'alpine:3')
        ->execute(['sh', '-lc', 'echo "hello from podman sandbox"']);

    assert($result->exitCode() === 0, 'Podman echo should exit with code 0');
    echo "Exit: {$result->exitCode()}\n";
    echo $result->stdout();
} catch (Throwable $e) {
    echo "Podman sandbox unavailable: {$e->getMessage()}\n";
}
?>
```
