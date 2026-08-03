---
title: 'Describe an AgentLoop'
docname: 'agent_describe'
order: 10
id: 'agent-describe'
tags:
  - 'agents'
  - 'agent-loop'
  - 'observability'
---
## Overview

`AgentLoop::describe()` returns a deterministic, credential-safe description of
the loop's resolved runtime. This low-level example uses `AgentLoop` directly and
does not install AgentBuilder capabilities.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\AgentLoop;
use Cognesy\Agents\Tool\Tools\BaseTool;

final class HealthCheckTool extends BaseTool
{
    public function __construct() {
        parent::__construct(
            name: 'health_check',
            description: 'Report whether the local process is healthy.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        return 'healthy';
    }
}

$agent = AgentLoop::default()->withTool(new HealthCheckTool());
$description = $agent->describe();

echo $description->toMarkdown(), "\n\n";
echo json_encode($description->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";

assert($description->toArray()['tools'][0]['name'] === 'health_check');
assert($description->toArray()['capabilities'] === []);
?>
```
