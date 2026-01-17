<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Agent;

use Cognesy\Addons\Agent\Definitions\AgentDefinitionParser;
use InvalidArgumentException;

describe('AgentDefinitionParser', function () {
    it('parses a full agent definition', function () {
        $yaml = <<<'YAML'
version: 1
id: partner-assistant
name: Partner Assistant
description: Partner management helper
blueprint: basic
system_prompt: |
  You are a Partner Management Assistant.
llm:
  preset: anthropic
  model: claude-haiku-4-5-20251001
  temperature: 0.5
  max_output_tokens: 2048
execution:
  max_steps: 8
  max_tokens: 50000
  timeout_sec: 300
  error_policy: stop_on_any_error
  error_policy_max_retries: 2
tools:
  allow: [tools, actions, invoke_action]
  deny: [bash]
capabilities:
  - tool_discovery
  - work_context
metadata:
  cost_tier: low
YAML;

        $definition = (new AgentDefinitionParser())->parseYamlString($yaml);

        expect($definition->version)->toBe(1);
        expect($definition->id)->toBe('partner-assistant');
        expect($definition->name)->toBe('Partner Assistant');
        expect($definition->description)->toBe('Partner management helper');
        expect($definition->systemPrompt)->toContain('Partner Management Assistant');
        expect($definition->blueprint)->toBe('basic');
        expect($definition->blueprintClass)->toBeNull();
        expect($definition->llm->preset)->toBe('anthropic');
        expect($definition->llm->model)->toBe('claude-haiku-4-5-20251001');
        expect($definition->llm->temperature)->toBe(0.5);
        expect($definition->llm->maxOutputTokens)->toBe(2048);
        expect($definition->execution->maxSteps)->toBe(8);
        expect($definition->execution->maxTokens)->toBe(50000);
        expect($definition->execution->timeoutSec)->toBe(300);
        expect($definition->execution->errorPolicy)->toBe('stop_on_any_error');
        expect($definition->execution->errorPolicyMaxRetries)->toBe(2);
        expect($definition->tools->allow)->toBe(['tools', 'actions', 'invoke_action']);
        expect($definition->tools->deny)->toBe(['bash']);
        expect($definition->capabilities)->toBe(['tool_discovery', 'work_context']);
        expect($definition->metadata)->toBe(['cost_tier' => 'low']);
    });

    it('parses a minimal agent definition', function () {
        $yaml = <<<'YAML'
version: 1
id: minimal-agent
name: Minimal Agent
description: Minimal
system_prompt: Run minimal mode.
llm:
  preset: openai
YAML;

        $definition = (new AgentDefinitionParser())->parseYamlString($yaml);

        expect($definition->blueprint)->toBeNull();
        expect($definition->blueprintClass)->toBeNull();
        expect($definition->execution->maxSteps)->toBeNull();
        expect($definition->tools->allow)->toBeNull();
        expect($definition->capabilities)->toBe([]);
        expect($definition->metadata)->toBe([]);
    });

    it('rejects missing required fields', function () {
        $yaml = <<<'YAML'
version: 1
name: Missing ID
description: Missing ID
system_prompt: Something
llm:
  preset: anthropic
YAML;

        $parse = fn() => (new AgentDefinitionParser())->parseYamlString($yaml);

        expect($parse)->toThrow(InvalidArgumentException::class);
    });

    it('rejects unknown root keys', function () {
        $yaml = <<<'YAML'
version: 1
id: extra
name: Extra
description: Extra
system_prompt: Extra
llm:
  preset: anthropic
extra: nope
YAML;

        $parse = fn() => (new AgentDefinitionParser())->parseYamlString($yaml);

        expect($parse)->toThrow(InvalidArgumentException::class);
    });

    it('rejects conflicting blueprint fields', function () {
        $yaml = <<<'YAML'
version: 1
id: conflict
name: Conflict
description: Conflict
system_prompt: Conflict
blueprint: basic
blueprint_class: Acme\Agent\BasicAgent
llm:
  preset: anthropic
YAML;

        $parse = fn() => (new AgentDefinitionParser())->parseYamlString($yaml);

        expect($parse)->toThrow(InvalidArgumentException::class);
    });
});
