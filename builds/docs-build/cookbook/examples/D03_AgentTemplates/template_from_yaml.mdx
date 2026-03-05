---
title: 'Template from YAML'
docname: 'template_from_yaml'
order: 3
id: 'c7f3'
---
## Overview

Load `AgentDefinition` from YAML and run it with the same factories.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Agents\Template\AgentDefinitionLoader;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;

$logger = new AgentEventConsoleObserver(useColors: true, showTimestamps: true, showContinuation: true);

$definition = (new AgentDefinitionLoader())
    ->loadFile('examples/D03_AgentTemplates/TemplateFromYaml/agent.yaml');

$capabilities = new AgentCapabilityRegistry();

$loop = (new DefinitionLoopFactory($capabilities))
    ->instantiateAgentLoop($definition)
    ->wiretap($logger->wiretap());

$state = (new DefinitionStateFactory())->instantiateAgentState(
    $definition,
    AgentState::empty()->withUserMessage('What is 7 multiplied by 8?'),
);

echo "=== Agent Execution Log ===\n\n";
$final = $loop->execute($state);

echo "\n=== Result ===\n";
echo "Template: {$definition->name}\n";
echo 'Answer: ' . ($final->finalResponse()->toString() ?: 'No response') . "\n";
echo 'Status: ' . $final->status()->value . "\n";

assert($definition->name === 'yaml-agent');
assert(in_array($final->status()->value, ['completed', 'failed'], true));
?>
```
