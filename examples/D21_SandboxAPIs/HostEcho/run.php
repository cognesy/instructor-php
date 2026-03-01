---
title: 'Sandbox API: Host'
docname: 'sandbox_api_host_echo'
id: 'd210'
---
## Overview

Minimal host driver API check.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;

$policy = ExecutionPolicy::in(__DIR__);

$result = Sandbox::host($policy)->execute(['echo', 'hello from host sandbox']);

echo "Exit: {$result->exitCode()}\n";
echo $result->stdout();
?>
```
