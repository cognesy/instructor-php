---
title: 'Round-Trip an Agent Definition'
docname: 'agent_definition_round_trip'
order: 9
id: 'agent-definition-round-trip'
tags:
  - 'agents'
  - 'agent-templates'
  - 'definitions'
---
## Overview

Persist a canonical agent definition as Markdown, load it through the registry,
then instantiate both its loop and seed state. The full path is deterministic and
does not contact an LLM.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Capability\AgentCapabilityRegistry;
use Cognesy\Agents\Collections\NameList;
use Cognesy\Agents\Template\AgentDefinitionRegistry;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Agents\Template\Factory\DefinitionLoopFactory;
use Cognesy\Agents\Template\Factory\DefinitionStateFactory;
use Cognesy\Agents\Template\FileAgentDefinitionStore;
use Cognesy\Agents\Tool\ToolRegistry;
use Cognesy\Agents\Tool\Tools\BaseTool;

final class ReleaseStatusTool extends BaseTool
{
    public function __construct() {
        parent::__construct(
            name: 'release_status',
            description: 'Report deterministic release readiness.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        return 'ready';
    }
}

$definition = new AgentDefinition(
    name: 'release-reviewer',
    description: 'Reviews a release before publication.',
    systemPrompt: 'Check release notes, tests, and package versions.',
    tools: new NameList('release_status'),
);
$directory = sys_get_temp_dir() . '/agent-definition-example-' . bin2hex(random_bytes(6));
mkdir($directory, 0755, true);

try {
    $stored = (new FileAgentDefinitionStore($directory))->save($definition);
    $definitions = new AgentDefinitionRegistry();
    $definitions->loadFromFile($stored->path);
    $loaded = $definitions->get('release-reviewer');

    $tools = new ToolRegistry();
    $tools->register(new ReleaseStatusTool());
    $loop = (new DefinitionLoopFactory(new AgentCapabilityRegistry(), $tools))
        ->instantiateAgentLoop($loaded);
    $state = (new DefinitionStateFactory())->instantiateAgentState($loaded);

    echo file_get_contents($stored->path), "\n";
    echo $loop->describe()->toMarkdown(), "\n";

    assert($loaded->canonicalArray() === $definition->canonicalArray());
    assert($loop->profile()->name() === 'release-reviewer');
    assert($loop->tools()->names() === ['release_status']);
    assert($state->context()->systemPrompt() === $definition->systemPrompt);
} finally {
    if (isset($stored) && is_file($stored->path)) {
        unlink($stored->path);
    }
    rmdir($directory);
}
?>
```
