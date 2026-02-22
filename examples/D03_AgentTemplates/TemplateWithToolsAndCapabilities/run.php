---
title: 'Template with Tools and Capabilities'
docname: 'template_with_tools_and_capabilities'
order: 4
id: 'd4a8'
---
## Overview

Template declares both a capability (`guards.basic`) and a tool allow-list.
The loop factory resolves both from registries while using a real LLM driver.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Events\Support\AgentEventConsoleObserver;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Agents\Tool\Tools\MockTool;

$logger = new AgentEventConsoleObserver(useColors: true, showTimestamps: true, showContinuation: true);

$capabilities = new AgentCapabilityRegistry();
$capabilities->register('guards.basic', new UseGuards(maxSteps: 4, maxTokens: 2000, maxExecutionTime: 30));

$tools = new ToolRegistry();
$tools->register(MockTool::returning(
    'city_fact',
    'Returns one city fact',
    'Paris has a population of about 2.1 million residents.',
));

$definition = new AgentDefinition(
    name: 'tool-agent',
    description: 'Template-declared tools and capabilities.',
    systemPrompt: 'Use tools when needed.',
    llmConfig: 'openai',
    capabilities: NameList::fromArray(['guards.basic']),
    tools: NameList::fromArray(['city_fact']),
);

$loop = (new DefinitionLoopFactory($capabilities, $tools))
    ->instantiateAgentLoop($definition)
    ->wiretap($logger->wiretap());

$final = $loop->execute(AgentState::empty()->withUserMessage(
    'Use city_fact and answer with one short fact about Paris.',
));

echo "=== Result ===\n";
echo 'Steps: ' . $final->stepCount() . "\n";
echo 'Final answer: ' . ($final->finalResponse()->toString() ?: 'No response') . "\n";

assert($final->status()->value === 'completed');
assert($final->finalResponse()->toString() !== '');
?>
```
