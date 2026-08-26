---
title: 'Tell Harness: External One-Run Protocol'
docname: 'tell_harness_external_protocol'
order: 12
id: 'd1112'
tags:
  - 'no-replay'
  - 'tell'
  - 'tell-harness'
  - 'protocol'
---
## Overview

Drive one Tell run from a process that is not linked to the PHP SDK. The shell
controller beside this example writes one versioned request to stdin and reads
ordered JSONL frames from stdout. It can be replaced by any language that can
spawn a process and parse one JSON object per line.

## Example

```php
<?php
require 'examples/boot.php';

$controller = __DIR__.'/controller.sh';
$command = [
    'bash',
    $controller,
    'Inspect this project and report one actionable risk.',
];
$process = proc_open(
    $command,
    [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
    $pipes,
    dirname(__DIR__, 3),
);
if (! is_resource($process)) {
    throw new RuntimeException('Unable to start the Tell shell controller.');
}

fclose($pipes[0]);
while (($line = fgets($pipes[1])) !== false) {
    $frame = json_decode($line, true, flags: JSON_THROW_ON_ERROR);
    echo $frame['sequence'].' '.$frame['type']."\n";
}
$errors = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exit = proc_close($process);

if ($errors !== '') {
    fwrite(STDERR, $errors);
}
if ($exit !== 0) {
    throw new RuntimeException("Tell agent protocol exited with {$exit}.");
}
```

## Key Points

- `controller.sh` is the actual non-PHP controller; this cookbook wrapper only
  makes it runnable through Instructor Hub.
- Stdout contains only `tell.agent.frame.v1` JSONL. Human diagnostics go to
  stderr, so a controller never has to separate prose from protocol data.
- One invocation consumes one request, produces zero or more progress frames,
  then exactly one `result`, `error`, or `cancelled` terminal frame.
- This live example uses the configured default Tell provider and is therefore
  tagged `no-replay`. The package subprocess tests use the public deterministic
  driver and require no provider credential.
