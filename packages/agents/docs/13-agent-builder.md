---
title: 'AgentBuilder & Capabilities'
description: 'Build AgentLoop instances from reusable capabilities'
---

# AgentBuilder & Capabilities

`AgentBuilder` is the composition layer for `AgentLoop`.
Use it when you want reusable setup instead of wiring tools, hooks, and drivers by hand.

## Quick Start

```php
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Agents\Data\AgentState;

$agent = AgentBuilder::base()
    ->withCapability(new UseLLMConfig(preset: 'anthropic'))
    ->withCapability(new UseBash())
    ->withCapability(new UseGuards(maxSteps: 20, maxTokens: 32768))
    ->build();

$state = AgentState::empty()->withUserMessage('List files in /tmp');
$result = $agent->execute($state);
```

## API

```php
$builder = AgentBuilder::base();
$builder = $builder->withCapability($capability);
$agent = $builder->build(); // AgentLoop
```

`AgentBuilder` is immutable. Every `withCapability()` call returns a new builder.

## Common Recipes

### Add a custom tool

```php
use Cognesy\Agents\Capability\Core\UseTools;

$agent = AgentBuilder::base()
    ->withCapability(new UseTools($myTool))
    ->build();
```

### Replace the driver

```php
use Cognesy\Agents\Capability\Core\UseDriver;

$agent = AgentBuilder::base()
    ->withCapability(new UseDriver($driver))
    ->build();
```

### Add a hook

```php
use Cognesy\Agents\Capability\Core\UseHook;
use Cognesy\Agents\Hook\Collections\HookTriggers;
use Cognesy\Agents\Hook\Hooks\CallableHook;

$agent = AgentBuilder::base()
    ->withCapability(new UseHook(
        hook: new CallableHook(fn($ctx) => $ctx),
        triggers: HookTriggers::afterStep(),
        priority: 10,
        name: 'after_step_noop',
    ))
    ->build();
```

### Wrap the default message compiler

```php
use Cognesy\Agents\Capability\Core\UseContextCompilerDecorator;
use Cognesy\Agents\Context\CanCompileMessages;

$agent = AgentBuilder::base()
    ->withCapability(new UseContextCompilerDecorator(
        fn(CanCompileMessages $inner) => new TokenLimitCompiler($inner, maxTokens: 4000)
    ))
    ->build();
```

### Enable subagents

```php
use Cognesy\Agents\Capability\Subagent\UseSubagents;

$agent = AgentBuilder::base()
    ->withCapability(new UseSubagents(provider: $registry))
    ->build();
```

## Built-in Capabilities

### Core capabilities

- `UseLLMConfig` - set model/provider config
- `UseGuards` - step/token/time/finish guards
- `UseTools` - add tools
- `UseHook` - add one hook
- `UseDriver` - replace driver
- `UseDriverDecorator` - wrap current driver
- `UseContextCompiler` - replace compiler
- `UseContextCompilerDecorator` - wrap compiler
- `UseContextConfig` - set system prompt and response format
- `UseReActConfig` - configure ReAct driver
- `UseToolFactory` - resolve tools at build time

### Domain capabilities

- `UseBash`
- `UseFileTools`
- `UseSubagents`
- `UsePlanningSubagent`
- `UseStructuredOutputs`
- `UseSummarization`
- `UseSelfCritique`
- `UseSkills`
- `UseTaskPlanning`
- `UseMetadataTools`
- `UseToolRegistry`
- `UseExecutionHistory`
- `UseExecutionRetrospective`

## Build-time Resolution

When `build()` runs, the configurator resolves components in this order:

1. message compiler
2. tool-use driver
3. concrete tools (including deferred tools)
4. interceptor from hook stack

This matters for deferred tools that need access to the final driver or event handler.

## AgentBuilder vs AgentLoop

Use `AgentLoop` directly when you need a small one-off setup.
Use `AgentBuilder` when the setup should be reusable, testable, and shared across agents.

## Related

- [Basic Agent](02-basic-agent.md)
- [Hooks](08-hooks.md)
- [Subagents](15-subagents.md)
- [Session Runtime](16-session-runtime.md)
