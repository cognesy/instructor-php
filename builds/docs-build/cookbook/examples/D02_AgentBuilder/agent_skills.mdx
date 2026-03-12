---
title: 'Agent Skills - SKILL.md Open Standard'
docname: 'agent_skills'
order: 10
id: 'sk01'
---
## Overview

Skills extend agents with reusable instruction modules. Each skill is a `SKILL.md` file
with YAML frontmatter and markdown instructions, following the Agent Skills Open Standard
(agentskills.io) — compatible with 30+ AI tools.

This example demonstrates:
- `SkillLibrary`: discovering and loading skills from a directory
- `UseSkills`: wiring skills into an agent via `AgentBuilder`
- Argument substitution (`$ARGUMENTS`, `$0`, `$1`)
- Shell preprocessing for dynamic content injection
- Invocation control (`disable-model-invocation`, `user-invocable`)
- `LoadSkillTool`: the tool the LLM uses to load skills on demand

The example uses `FakeAgentDriver` for deterministic execution — no real LLM calls.

## Example

```php
<?php
require 'examples/boot.php';

use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Skills\SkillLibrary;
use Cognesy\Agents\Capability\Skills\SkillPreprocessor;
use Cognesy\Agents\Capability\Skills\UseSkills;

// -- Step 1: Discover skills from the example skills directory -----------

$library = SkillLibrary::inDirectory(__DIR__ . '/skills');

echo "=== All skills ===\n";
echo $library->renderSkillList() . "\n\n";

echo "=== Model-invocable only (excludes disable-model-invocation: true) ===\n";
echo $library->renderSkillList(modelInvocable: true) . "\n\n";

echo "=== User-invocable only ===\n";
echo $library->renderSkillList(userInvocable: true) . "\n\n";

// -- Step 2: Load a skill with argument substitution --------------------

$skill = $library->getSkill('fix-issue');
echo "=== fix-issue skill rendered with arguments ===\n";
echo $skill->render('42') . "\n\n";

$skill = $library->getSkill('code-review');
echo "=== code-review skill rendered with arguments ===\n";
echo $skill->render('src/Auth/LoginController.php') . "\n\n";

// -- Step 3: Shell preprocessing (command substitution) ----------------

$preprocessor = new SkillPreprocessor(timeoutSeconds: 5);
$skill = $library->getSkill('project-context');
echo "=== project-context skill (raw body, before preprocessing) ===\n";
echo $skill->body . "\n\n";

echo "=== project-context skill (after preprocessing + argument substitution) ===\n";
// Preprocessing is automatic when LoadSkillTool has a preprocessor,
// but we can also call it directly for demonstration:
$processedBody = $preprocessor->process($skill->body);
echo $processedBody . "\n\n";

// -- Step 4: Wire skills into an agent ----------------------------------

$agent = AgentBuilder::base()
    ->withCapability(new UseGuards(maxSteps: 3))
    ->withCapability(new UseSkills($library, $preprocessor))
    ->build();

echo "=== Agent built with skills capability ===\n";
echo "Tools registered: " . implode(', ', $agent->tools()->names()) . "\n";
echo "Done.\n";
?>
```
