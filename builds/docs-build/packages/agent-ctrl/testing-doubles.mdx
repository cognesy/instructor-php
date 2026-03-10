---
title: Testing Doubles
description: 'Deterministic testing seams for AgentCtrl.'
---

## Overview

`agent-ctrl` is different from `instructor`, `polyglot`, and `agents`.

It does not currently ship a package-native `FakeAgentCtrl` or fake bridge inside
`packages/agent-ctrl`. The main deterministic seams are lower or higher in the stack:

- unit-test command building and response parsing directly
- use `FakeSandbox` when you need deterministic command execution without running a real CLI
- use `AgentCtrlFake` only when you are testing the Laravel facade layer

## In-Package Unit Tests

Most `agent-ctrl` logic is easiest to test at the pure-object level.

That includes:

- config normalization with `AgentConfig`
- command building
- response parsing
- session and DTO behavior

Prefer this seam whenever the test does not need process execution.

## `FakeSandbox`

When the test crosses into execution, use `FakeSandbox` from the sandbox package.

This is the right seam for:

- deterministic command execution
- timeout and exit-code scenarios
- stdout and stderr handling
- process-free coverage for bridge execution paths

`FakeSandbox` is the main fake that matters for core `agent-ctrl` execution tests.

## Laravel `AgentCtrlFake`

If you are testing the Laravel integration, use `AgentCtrlFake` from the Laravel package.

That fake belongs to the facade layer, not to core `agent-ctrl`.

Use it when you want to test:

- facade-based application code
- queued fake responses at the framework boundary
- execution assertions in Laravel feature tests

## Which One To Use

Use this rule of thumb:

- pure package logic: test the value objects and builders directly
- command execution paths: use `FakeSandbox`
- Laravel integration paths: use `AgentCtrlFake`

Until `agent-ctrl` grows a package-native fake bridge, those are the current
deterministic seams to rely on.
