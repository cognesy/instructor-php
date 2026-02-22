---
title: 'Template Overrides Seed State'
docname: 'template_override_seed_state'
order: 5
id: 'e1bc'
---
## Overview

Show how `DefinitionStateFactory` merges template data with a provided seed state.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Data\AgentBudget;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Utils\Metadata;

$definition = new AgentDefinition(
    name: 'seed-override-agent',
    description: 'Shows seed + template merge behavior.',
    systemPrompt: 'Template prompt overrides seed prompt.',
    budget: new AgentBudget(maxSteps: 3),
    metadata: Metadata::fromArray(['tier' => 'gold', 'region' => 'eu']),
);

$seed = AgentState::empty()
    ->withUserMessage('Keep this message from seed state.')
    ->withSystemPrompt('Seed prompt should be replaced.')
    ->withMetadata('session', 'abc-123')
    ->withBudget(new AgentBudget(maxSteps: 10));

$state = (new DefinitionStateFactory())->instantiateAgentState($definition, $seed);

echo "=== Result ===\n";
echo 'System prompt: ' . $state->context()->systemPrompt() . "\n";
echo 'Messages count: ' . $state->messages()->count() . "\n";
echo 'Budget maxSteps: ' . ($state->budget()->maxSteps ?? 0) . "\n";
echo 'Metadata tier: ' . $state->metadata()->get('tier') . "\n";
echo 'Metadata session: ' . $state->metadata()->get('session') . "\n";

assert($state->context()->systemPrompt() === 'Template prompt overrides seed prompt.');
assert($state->messages()->count() === 1);
assert($state->budget()->maxSteps === 3);
assert($state->metadata()->get('tier') === 'gold');
assert($state->metadata()->get('session') === 'abc-123');
?>
```
