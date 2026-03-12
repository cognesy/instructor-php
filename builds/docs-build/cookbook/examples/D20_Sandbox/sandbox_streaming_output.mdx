---
title: 'Sandbox: Streaming Output'
docname: 'sandbox_streaming_output'
id: 'd203'
---
## Overview

Stream command output as it arrives.
This is useful for long-running tool calls where users need live feedback.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Sandbox;
use Symfony\Component\Process\Process;

$policy = ExecutionPolicy::in(__DIR__)
    ->withTimeout(10);

$command = [
    'php',
    '-r',
    'for ($i = 1; $i <= 3; $i++) { echo "tick {$i}\\n"; usleep(300000); } fwrite(STDERR, "stderr line\\n");',
];

$result = Sandbox::host($policy)->execute(
    argv: $command,
    onOutput: function (string $type, string $chunk): void {
        $stream = $type === Process::ERR ? 'ERR' : 'OUT';
        echo "[{$stream}] {$chunk}";
    },
);

echo "\nDone in {$result->duration()}s, exit={$result->exitCode()}\n";

assert($result->exitCode() === 0, 'Streaming command should exit with code 0');
?>
```
