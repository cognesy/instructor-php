---
title: 'Install Agent Self-Description'
docname: 'agent_self_description'
order: 13
id: 'agent-self-description'
tags:
  - 'agents'
  - 'agent-profile'
  - 'observability'
---
## Overview

`AgentLoop::describe()` exposes a deterministic, credential-safe description of the
agent that was actually built. `UseSelfDescription` also installs the opt-in
`describe_self` tool so the agent can inspect the same resolved profile during an
execution.

This example performs no inference and needs no API credentials.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Describe\UseSelfDescription;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Profile\AgentIdentity;

$agent = AgentBuilder::base()
    ->withIdentity(new AgentIdentity(
        name: 'release_assistant',
        description: 'Reviews release changes and reports what it can do.',
    ))
    ->withCapability(new UseSystemPrompt())
    ->withCapability(new UseSelfDescription())
    ->build();

$description = $agent->describe();

echo $description->toMarkdown(), "\n\n";
echo json_encode($description->toArray(), JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), "\n";

assert(in_array(
    'describe_self',
    array_column($description->toArray()['tools'], 'name'),
    true,
));
?>
```
