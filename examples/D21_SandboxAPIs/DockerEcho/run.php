---
title: 'Sandbox API: Docker'
docname: 'sandbox_api_docker_echo'
id: 'd211'
---
## Overview

Minimal docker driver API check.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;

$policy = ExecutionPolicy::in(__DIR__)
    ->withTimeout(10);

try {
    $result = Sandbox::docker($policy, image: 'alpine:3')
        ->execute(['sh', '-lc', 'echo "hello from docker sandbox"']);

    echo "Exit: {$result->exitCode()}\n";
    echo $result->stdout();
} catch (Throwable $e) {
    echo "Docker sandbox unavailable: {$e->getMessage()}\n";
}
?>
```
