---
title: 'Template from Definition'
docname: 'template_from_definition'
order: 1
id: 'a9d1'
---
## Overview

Instantiate an agent directly from an in-memory `AgentDefinition`.

Key concepts:
- `AgentDefinition`: template data object
- `DefinitionLoopFactory`: builds executable loop from template
- `DefinitionStateFactory`: builds initial state from template
- `llmConfig`: selects real LLM provider preset for execution
- `AgentEventConsoleObserver`: execution visibility

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Data\AgentBudget;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;

$logger = new AgentEventConsoleObserver(useColors: true, showTimestamps: true, showContinuation: true);

$capabilities = new AgentCapabilityRegistry();

$definition = new AgentDefinition(
    name: 'geo-agent',
    description: 'Answers short geography questions.',
    systemPrompt: 'You are concise and precise.',
    llmConfig: 'openai',
    budget: new AgentBudget(maxSteps: 3),
);

$loop = (new DefinitionLoopFactory($capabilities))
    ->instantiateAgentLoop($definition)
    ->wiretap($logger->wiretap());

$seed = AgentState::empty()->withUserMessage('What is the capital of France?');
$state = (new DefinitionStateFactory())->instantiateAgentState($definition, $seed);

echo "=== Agent Execution Log ===\n\n";
$final = $loop->execute($state);

echo "\n=== Result ===\n";
echo 'Answer: ' . ($final->finalResponse()->toString() ?: 'No response') . "\n";
echo 'Steps: ' . $final->stepCount() . "\n";
echo 'Status: ' . $final->status()->value . "\n";

assert(in_array($final->status()->value, ['completed', 'failed'], true));
assert($final->stepCount() >= 0);
?>
```
