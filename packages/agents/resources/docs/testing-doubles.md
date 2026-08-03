---
title: Testing Doubles
description: 'A quick map of the deterministic testing facilities in Agents.'
---

## Overview

Agents has several different testing seams. They solve different problems, so the
main question is which layer you want to isolate.

- use `FakeAgentDriver` to script whole agent-loop steps
- use `FakeInferenceDriver` when a test drives raw inference responses directly
- use `FakeTool` for deterministic tool execution
- use `FakeSubagentProvider` for subagent registry and lookup tests
- use `TestAgentLoop` when you need a small harness around loop stopping behavior
- use `FakeSandbox` from the sandbox package when testing bash or process-backed tools

## `FakeAgentDriver`

`FakeAgentDriver` is the main high-level fake for agent-loop tests.

Use it when you want to:

- script full loop steps with `ScenarioStep`
- test tool-call and final-response paths without any LLM calls
- drive subagent child steps with `withChildSteps()`

This is the best seam for most package-level agent behavior tests.

## `FakeInferenceDriver`

`FakeInferenceDriver` lives in `packages/agents/tests/Support`.

Use it when the test is closer to the raw inference boundary and you want queued:

- `InferenceResponse` objects for sync paths
- `PartialInferenceDelta` batches for streaming paths

This seam is narrower than `FakeAgentDriver`. It is useful when a test exercises
agent logic that still interacts with the Polyglot-style inference contract.

## `FakeTool`

`FakeTool` is the deterministic tool double for loop and registry tests.

Use it when you need:

- a fixed return value with `FakeTool::returning(...)`
- a custom callable-backed tool without building a full real tool class
- optional schema and metadata for tool-definition coverage

For detailed examples, see `10-testing.md` and `05-tools.md`.

## `FakeSubagentProvider`

`FakeSubagentProvider` is the in-memory agent-definition registry for subagent tests.

Use it when you need:

- deterministic subagent lookup
- explicit control over which definitions exist
- error-path coverage for missing subagents

## `TestAgentLoop`

`TestAgentLoop` is a test harness, not a fake. It subclasses `AgentLoop` and adds a
small stop condition based on a maximum iteration count.

Use it when the test needs a controllable loop wrapper rather than a different
driver or tool.

## `FakeSandbox`

For bash-backed or process-backed tools, pull in `FakeSandbox` from the sandbox package.

That is the right seam when the agent test still needs command execution behavior
but must stay deterministic and process-free.

## Which One To Use

Use this rule of thumb:

- `FakeAgentDriver` for most agent-loop behavior tests
- `FakeInferenceDriver` for raw inference-boundary tests
- `FakeTool` for deterministic tool execution
- `FakeSubagentProvider` for subagent lookup and registry behavior
- `TestAgentLoop` for loop-harness cases
- `FakeSandbox` when shell or process execution is part of the scenario
