# Major Agent Capability Gaps - Research & PRDs

**Date**: 2026-01-27
**Status**: Research
**Author**: Analysis based on comparison with Vercel AI SDK, Neuron AI, Pydantic AI

## Executive Summary

This research documents the major capability gaps in instructor-php's agent system compared to mature agent frameworks. Each gap is specified as a PRD with implementation insights from libraries that support these features.

## Gap Priority Matrix

| Gap | Impact | Effort | Priority |
|-----|--------|--------|----------|
| [Streaming Agent Loop](./01-streaming-agent-loop.md) | High | Low | P0 |
| [Tool Approval Workflow](./02-tool-approval-workflow.md) | High | Low | P0 |
| [Dynamic Tool Filtering](./03-dynamic-tool-filtering.md) | Medium | Low | P1 |
| [Model Middleware](./04-model-middleware.md) | Medium | Medium | P1 |
| [Subagent System](./05-subagent-system.md) | Medium | High | P2 |
| [OpenTelemetry Integration](./06-opentelemetry-integration.md) | Medium | Medium | P2 |
| [MCP Integration](./07-mcp-integration.md) | Low | High | P3 |

## Impact Assessment

### User Experience
- **Streaming**: Users can't see progress during long-running agents
- **Approval**: Sensitive operations require custom implementation
- **Dynamic Tools**: Can't guide agents through multi-phase tasks

### Developer Experience
- **Middleware**: Cross-cutting concerns scattered across codebase
- **Telemetry**: Production debugging requires manual instrumentation
- **Subagents**: Complex orchestration patterns need custom code

### Ecosystem
- **MCP**: Missing access to growing tool ecosystem
- **Interoperability**: Harder to integrate with observability platforms

## Comparison Frameworks

| Framework | Language | Primary Focus |
|-----------|----------|---------------|
| Vercel AI SDK | TypeScript | Streaming-first agents |
| Neuron AI | PHP | MCP-integrated agents |
| Pydantic AI | Python | Structured output + agents |
| LangChain | Python/JS | Comprehensive agent toolkit |
| AutoGen | Python | Multi-agent orchestration |

## Documents

1. [Streaming Agent Loop](./01-streaming-agent-loop.md) - Real-time step streaming
2. [Tool Approval Workflow](./02-tool-approval-workflow.md) - Human-in-the-loop for sensitive ops
3. [Dynamic Tool Filtering](./03-dynamic-tool-filtering.md) - Per-step tool availability
4. [Model Middleware](./04-model-middleware.md) - Composable model wrappers
5. [Subagent System](./05-subagent-system.md) - Native agent orchestration
6. [OpenTelemetry Integration](./06-opentelemetry-integration.md) - Production observability
7. [MCP Integration](./07-mcp-integration.md) - Model Context Protocol support
