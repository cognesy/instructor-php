# Overview

This repository contains the source code for the addons to Instructor, which are extra capabilities useful in certain scenarios.

Addons expand LLM capabilities but are not required for basic functionality.

## Key Components

- **Agent** - Tool-calling agent with iterative execution, state management, and extensible capabilities. See `AGENT.md`.
- **StepByStep** - Foundational architecture for iterative, state-based processes with configurable continuation criteria. See `STEPBYSTEP.md`.
- **ToolUse** - LLM tool calling with automatic execution and result handling. See `TOOLUSE.md`.
- **Chat** - Multi-participant conversations with configurable behavior. See `CHAT.md`.

## Recent Updates (2026-01-16)

- **Continuation Observability**: `ContinuationOutcome` and `ContinuationEvaluation` provide full decision tracing
- **Error Policy**: Configurable retry behavior with `ErrorPolicy::retryToolErrors()`, `stopOnAnyError()`, etc.
- **Time Tracking**: Per-execution timing (`executionStartedAt`) and cumulative timeout support
- **Message Helpers**: `isAssistant()`, `isTool()`, `isUser()`, `isSystem()`, `hasRole()` on Message class
