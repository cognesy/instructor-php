---
title: 'Template from Markdown'
docname: 'template_from_markdown'
order: 2
id: 'b2e4'
---
## Overview

Load `AgentDefinition` from markdown frontmatter and execute it.

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
    ->loadFile('examples/D03_AgentTemplates/TemplateFromMarkdown/agent.md');

$capabilities = new AgentCapabilityRegistry();

$loop = (new DefinitionLoopFactory($capabilities))
    ->instantiateAgentLoop($definition)
    ->wiretap($logger->wiretap());

$seed = AgentState::empty()->withUserMessage('Name one famous landmark in Paris.');
$state = (new DefinitionStateFactory())->instantiateAgentState($definition, $seed);

echo "=== Agent Execution Log ===\n\n";
$final = $loop->execute($state);

echo "\n=== Result ===\n";
echo "Template: {$definition->name}\n";
echo 'Answer: ' . ($final->finalResponse()->toString() ?: 'No response') . "\n";
echo 'Status: ' . $final->status()->value . "\n";

assert($definition->name === 'md-agent');
assert(in_array($final->status()->value, ['completed', 'failed'], true));
?>
```
