---
title: 'Derive a System Prompt from the Built Agent'
docname: 'agent_derived_prompt'
order: 11
id: 'agent-derived-prompt'
tags:
  - 'agents'
  - 'agent-builder'
  - 'system-prompt'
---
## Overview

`UseSystemPrompt` derives the available-tools and guidelines sections from the
resolved `AgentProfile`. The example executes the real prompt hook with a fake
driver, proving that the derived text reaches agent state without making a
provider request.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseDriver;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Capability\Prompt\UseSystemPrompt;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Drivers\Testing\FakeAgentDriver;
use Cognesy\Agents\Tool\Tools\BaseTool;

final class RepositorySearchTool extends BaseTool
{
    public function __construct() {
        parent::__construct(
            name: 'repository_search',
            description: 'Search repository files for a text pattern.',
        );
    }

    #[\Override]
    public function __invoke(mixed ...$args): string {
        return 'No matches in this deterministic example.';
    }
}

$agent = AgentBuilder::base()
    ->withCapability(new UseDriver(FakeAgentDriver::fromResponses('done')))
    ->withCapability(new UseTools(new RepositorySearchTool()))
    ->withCapability(new UseSystemPrompt(
        preamble: 'You are a careful repository assistant.',
    ))
    ->withCapability(new UseGuards(maxSteps: 1, maxTokens: null, maxExecutionTime: null))
    ->build();

$result = $agent->execute(AgentState::empty()->withUserMessage('Inspect the repository.'));
$systemPrompt = $result->context()->systemPrompt();

echo $systemPrompt, "\n";

assert(str_contains($systemPrompt, 'You are a careful repository assistant.'));
assert(str_contains($systemPrompt, '- repository_search: Search repository files'));
assert($agent->profile()->tools->names() === ['repository_search']);
?>
```
