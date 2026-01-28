# PRD: OpenTelemetry Integration

**Priority**: P2
**Impact**: Medium
**Effort**: Medium
**Status**: Proposed

## Problem Statement

instructor-php has no native OpenTelemetry integration for production observability. Developers must manually instrument agent execution, making it difficult to:
1. Trace multi-step agent workflows
2. Monitor LLM latency and token usage
3. Debug production issues
4. Integrate with existing observability platforms (Datadog, Jaeger, etc.)

## Current State

```php
// Current: Manual logging/metrics via observer
class MetricsObserver extends PassThroughObserver {
    public function beforeStep(AgentState $state): AgentState {
        $this->metrics->startTimer('agent.step');
        return $state;
    }

    public function afterStep(AgentStep $step, AgentState $state): AgentState {
        $this->metrics->endTimer('agent.step');
        $this->metrics->increment('agent.steps.total');
        $this->metrics->record('agent.tokens', $step->usage()?->total() ?? 0);
        return $state;
    }
}

// Custom tracing wrapper
class TracingDriver implements CanUseTools {
    public function useTools(...): AgentStep {
        $span = $this->tracer->startSpan('llm.inference');
        try {
            $result = $this->inner->useTools(...);
            $span->setAttribute('tokens', $result->usage()?->total());
            return $result;
        } finally {
            $span->end();
        }
    }
}
```

**Limitations**:
1. No standard span hierarchy for agent execution
2. No semantic conventions for LLM operations
3. Tool execution not automatically traced
4. Context not propagated to subagents
5. Each project reinvents tracing

## Proposed Solution

### Trace Hierarchy

```
agent.execute
├── agent.step[0]
│   ├── llm.inference
│   │   └── http.request (to provider)
│   ├── tool.execute[search]
│   └── tool.execute[analyze]
├── agent.step[1]
│   ├── llm.inference
│   └── tool.execute[summarize]
└── agent.step[2]
    └── llm.inference (final response)
```

### OpenTelemetry Provider

```php
use OpenTelemetry\API\Trace\TracerInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\SemConv\TraceAttributes;

class OpenTelemetryProvider {
    private TracerInterface $tracer;
    private MeterInterface $meter;

    public function __construct(
        ?TracerInterface $tracer = null,
        ?MeterInterface $meter = null,
        string $serviceName = 'instructor-php',
    ) {
        $this->tracer = $tracer ?? Globals::tracerProvider()
            ->getTracer($serviceName);
        $this->meter = $meter ?? Globals::meterProvider()
            ->getMeter($serviceName);
    }

    public function startAgentSpan(
        string $agentId,
        AgentState $state,
    ): SpanInterface {
        return $this->tracer->spanBuilder('agent.execute')
            ->setSpanKind(SpanKind::KIND_INTERNAL)
            ->setAttribute('agent.id', $agentId)
            ->setAttribute('agent.model', $state->model() ?? 'unknown')
            ->setAttribute('agent.tools.count', $state->toolsCount())
            ->startSpan();
    }

    public function startStepSpan(
        int $stepNumber,
        SpanInterface $parentSpan,
    ): SpanInterface {
        return $this->tracer->spanBuilder('agent.step')
            ->setParent(Context::getCurrent()->withContextValue($parentSpan))
            ->setAttribute('agent.step.number', $stepNumber)
            ->startSpan();
    }

    public function startInferenceSpan(
        string $model,
        int $messageCount,
        int $toolCount,
        SpanInterface $parentSpan,
    ): SpanInterface {
        return $this->tracer->spanBuilder('llm.inference')
            ->setParent(Context::getCurrent()->withContextValue($parentSpan))
            ->setSpanKind(SpanKind::KIND_CLIENT)
            ->setAttribute('llm.model', $model)
            ->setAttribute('llm.messages.count', $messageCount)
            ->setAttribute('llm.tools.count', $toolCount)
            ->startSpan();
    }

    public function startToolSpan(
        string $toolName,
        string $toolCallId,
        SpanInterface $parentSpan,
    ): SpanInterface {
        return $this->tracer->spanBuilder('tool.execute')
            ->setParent(Context::getCurrent()->withContextValue($parentSpan))
            ->setAttribute('tool.name', $toolName)
            ->setAttribute('tool.call_id', $toolCallId)
            ->startSpan();
    }
}
```

### Semantic Attributes

```php
// LLM-specific semantic conventions
class LLMAttributes {
    // Request attributes
    public const LLM_VENDOR = 'llm.vendor';           // 'openai', 'anthropic'
    public const LLM_MODEL = 'llm.model';             // 'gpt-4o', 'claude-3'
    public const LLM_TEMPERATURE = 'llm.temperature';
    public const LLM_MAX_TOKENS = 'llm.max_tokens';
    public const LLM_MESSAGES_COUNT = 'llm.messages.count';
    public const LLM_TOOLS_COUNT = 'llm.tools.count';

    // Response attributes
    public const LLM_TOKENS_INPUT = 'llm.tokens.input';
    public const LLM_TOKENS_OUTPUT = 'llm.tokens.output';
    public const LLM_TOKENS_TOTAL = 'llm.tokens.total';
    public const LLM_FINISH_REASON = 'llm.finish_reason';
    public const LLM_LATENCY_MS = 'llm.latency_ms';

    // Agent attributes
    public const AGENT_ID = 'agent.id';
    public const AGENT_STEP_NUMBER = 'agent.step.number';
    public const AGENT_STEP_COUNT = 'agent.step.count';
    public const AGENT_STOP_REASON = 'agent.stop_reason';

    // Tool attributes
    public const TOOL_NAME = 'tool.name';
    public const TOOL_CALL_ID = 'tool.call_id';
    public const TOOL_SUCCESS = 'tool.success';
    public const TOOL_ERROR = 'tool.error';
    public const TOOL_LATENCY_MS = 'tool.latency_ms';
}
```

### Instrumented Observer

```php
class OpenTelemetryObserver implements CanObserveAgentLifecycle {
    private OpenTelemetryProvider $otel;
    private ?SpanInterface $agentSpan = null;
    private ?SpanInterface $stepSpan = null;

    public function __construct(OpenTelemetryProvider $otel) {
        $this->otel = $otel;
    }

    public function onAgentStart(AgentState $state): AgentState {
        $this->agentSpan = $this->otel->startAgentSpan(
            $state->agentId(),
            $state
        );
        return $state;
    }

    public function beforeStep(AgentState $state): AgentState {
        $this->stepSpan = $this->otel->startStepSpan(
            $state->stepCount() + 1,
            $this->agentSpan
        );
        return $state;
    }

    public function afterStep(AgentStep $step, AgentState $state): AgentState {
        if ($this->stepSpan !== null) {
            $this->stepSpan->setAttribute(
                LLMAttributes::LLM_TOKENS_TOTAL,
                $step->usage()?->total() ?? 0
            );
            $this->stepSpan->setAttribute(
                LLMAttributes::LLM_FINISH_REASON,
                $step->finishReason()?->value ?? 'unknown'
            );
            $this->stepSpan->end();
            $this->stepSpan = null;
        }
        return $state;
    }

    public function beforeToolUse(ToolCall $call, AgentState $state): ToolUseDecision {
        $span = $this->otel->startToolSpan(
            $call->name(),
            $call->id(),
            $this->stepSpan
        );
        $state = $state->withMetadata("_otel_tool_span_{$call->id()}", $span);
        return ToolUseDecision::allow($call);
    }

    public function afterToolUse(ToolExecution $execution, AgentState $state): AgentState {
        $spanKey = "_otel_tool_span_{$execution->toolCall->id()}";
        $span = $state->metadata()->get($spanKey);

        if ($span instanceof SpanInterface) {
            $span->setAttribute(LLMAttributes::TOOL_SUCCESS, $execution->result->isSuccess());
            if ($execution->result->isFailure()) {
                $span->setAttribute(LLMAttributes::TOOL_ERROR, $execution->result->error());
                $span->setStatus(StatusCode::STATUS_ERROR);
            }
            $span->end();
        }

        return $state;
    }

    public function onAgentFinish(AgentState $state): AgentState {
        if ($this->agentSpan !== null) {
            $this->agentSpan->setAttribute(
                LLMAttributes::AGENT_STEP_COUNT,
                $state->stepCount()
            );
            $this->agentSpan->setAttribute(
                LLMAttributes::AGENT_STOP_REASON,
                $state->stopReason()?->value ?? 'unknown'
            );
            $this->agentSpan->end();
            $this->agentSpan = null;
        }
        return $state;
    }
}
```

### Metrics Collection

```php
class OpenTelemetryMetrics {
    private Counter $stepsCounter;
    private Counter $tokensCounter;
    private Counter $toolCallsCounter;
    private Histogram $stepLatency;
    private Histogram $toolLatency;
    private Histogram $inferenceLatency;

    public function __construct(MeterInterface $meter) {
        $this->stepsCounter = $meter->createCounter(
            'agent.steps.total',
            description: 'Total number of agent steps executed'
        );

        $this->tokensCounter = $meter->createCounter(
            'llm.tokens.total',
            description: 'Total tokens used across all LLM calls'
        );

        $this->toolCallsCounter = $meter->createCounter(
            'tool.calls.total',
            description: 'Total tool calls executed'
        );

        $this->stepLatency = $meter->createHistogram(
            'agent.step.duration',
            unit: 'ms',
            description: 'Duration of agent steps'
        );

        $this->toolLatency = $meter->createHistogram(
            'tool.duration',
            unit: 'ms',
            description: 'Duration of tool executions'
        );

        $this->inferenceLatency = $meter->createHistogram(
            'llm.inference.duration',
            unit: 'ms',
            description: 'Duration of LLM inference calls'
        );
    }

    public function recordStep(AgentStep $step, float $durationMs): void {
        $this->stepsCounter->add(1, [
            'model' => $step->model() ?? 'unknown',
            'finish_reason' => $step->finishReason()?->value ?? 'unknown',
        ]);

        $this->tokensCounter->add($step->usage()?->total() ?? 0, [
            'model' => $step->model() ?? 'unknown',
            'type' => 'step',
        ]);

        $this->stepLatency->record($durationMs, [
            'model' => $step->model() ?? 'unknown',
        ]);
    }

    public function recordToolCall(ToolExecution $execution, float $durationMs): void {
        $this->toolCallsCounter->add(1, [
            'tool' => $execution->toolCall->name(),
            'success' => $execution->result->isSuccess() ? 'true' : 'false',
        ]);

        $this->toolLatency->record($durationMs, [
            'tool' => $execution->toolCall->name(),
        ]);
    }
}
```

### Easy Integration

```php
// Simple usage with auto-configuration
$agent = new Agent(
    driver: $driver,
    tools: $tools,
    observer: OpenTelemetryObserver::autoConfigured(),
);

// Or with explicit configuration
$otel = new OpenTelemetryProvider(
    tracer: $customTracer,
    meter: $customMeter,
    serviceName: 'my-agent-service',
);

$agent = new Agent(
    driver: $driver,
    tools: $tools,
    observer: new CompositeObserver(
        new OpenTelemetryObserver($otel),
        new LoggingObserver($logger),
    ),
);
```

## How Other Libraries Implement This

### Vercel AI SDK

**Location**: `packages/ai/src/telemetry/telemetry.ts`

```typescript
// Built-in telemetry configuration
const result = await generateText({
    model: openai('gpt-4o'),
    prompt: 'Hello',
    experimental_telemetry: {
        isEnabled: true,
        functionId: 'my-function',
        metadata: { customKey: 'value' },
    },
});

// Automatic span creation
// Spans: ai.generateText > ai.generateText.doGenerate > ai.toolCall

// Semantic conventions from OpenTelemetry GenAI SIG
export const TELEMETRY_ATTRIBUTES = {
    'ai.operationId': 'unique operation id',
    'ai.model.id': 'model identifier',
    'ai.model.provider': 'provider name',
    'ai.usage.promptTokens': 'input tokens',
    'ai.usage.completionTokens': 'output tokens',
    'ai.finishReason': 'stop reason',
    'ai.toolCall.name': 'tool name',
    'ai.toolCall.id': 'tool call id',
};
```

**Key Implementation Details**:
1. `experimental_telemetry` option enables tracing
2. Uses OpenTelemetry SDK for Node.js
3. Follows GenAI semantic conventions working group
4. Records both spans and metrics
5. Context propagates through async operations

### LangChain

**Location**: `langchain/callbacks/tracers/langchain.py`

```python
# LangSmith integration (LangChain's observability platform)
from langchain import hub
from langchain.callbacks.tracers import LangChainTracer

tracer = LangChainTracer(project_name="my-project")

# Auto-traced execution
result = chain.invoke(
    {"input": "hello"},
    config={"callbacks": [tracer]}
)

# OpenTelemetry via opentelemetry-instrumentation-langchain
from opentelemetry.instrumentation.langchain import LangchainInstrumentor

LangchainInstrumentor().instrument()

# Now all LangChain operations are automatically traced
```

**Key Implementation Details**:
1. Native LangSmith integration
2. Third-party OpenTelemetry instrumentation
3. Callback-based tracing architecture
4. Supports custom metadata and tags
5. Run tree visualization

### Pydantic AI

**Location**: `pydantic_ai/telemetry.py`

```python
# Logfire integration (Pydantic's observability platform)
import logfire

logfire.configure()

# Auto-instrumentation
result = agent.run("Hello")

# Spans automatically created:
# - agent.run
# - model.request
# - tool.call (for each tool)

# Logfire uses OpenTelemetry under the hood
# Export to any OTLP-compatible backend
```

**Key Implementation Details**:
1. Native Logfire integration
2. OpenTelemetry-compatible export
3. Structured logging with tracing
4. Auto-instrumentation via decorators

### OpenLLMetry

**Location**: `traceloop/sdk-python`

```python
# OpenLLMetry - Open source LLM observability
from traceloop.sdk import Traceloop

Traceloop.init(app_name="my-app")

# Auto-instruments popular LLM libraries:
# - OpenAI
# - Anthropic
# - LangChain
# - LlamaIndex

# Exports to any OTLP backend
```

**Key Implementation Details**:
1. Library-agnostic instrumentation
2. Follows GenAI semantic conventions
3. Supports major LLM providers
4. Compatible with standard OTLP exporters

## Implementation Considerations

### Context Propagation for Subagents

```php
class SubAgentTool implements ToolInterface {
    public function use(mixed ...$args): Result {
        // Get current span from parent context
        $parentSpan = Span::getCurrent();

        // Create child context
        $childContext = $parentSpan->storeInContext(Context::getCurrent());

        // Pass to subagent
        $childState = $childState->withMetadata('_otel_context', $childContext);

        // Subagent observer will use this context
        return $this->subagent->execute($childState);
    }
}

// In subagent's OpenTelemetryObserver
public function onAgentStart(AgentState $state): AgentState {
    $parentContext = $state->metadata()->get('_otel_context');

    $this->agentSpan = $this->tracer->spanBuilder('agent.execute')
        ->setParent($parentContext ?? Context::getCurrent())
        ->startSpan();

    return $state;
}
```

### Sensitive Data Handling

```php
class OpenTelemetryObserver implements CanObserveAgentLifecycle {
    private bool $recordMessages;
    private bool $recordToolArgs;

    public function __construct(
        OpenTelemetryProvider $otel,
        bool $recordMessages = false,  // Off by default
        bool $recordToolArgs = false,  // Off by default
    ) {
        $this->recordMessages = $recordMessages;
        $this->recordToolArgs = $recordToolArgs;
    }

    public function beforeToolUse(ToolCall $call, AgentState $state): ToolUseDecision {
        $span = $this->otel->startToolSpan(...);

        if ($this->recordToolArgs) {
            // Sanitize sensitive fields
            $args = $this->sanitizeArgs($call->args());
            $span->setAttribute('tool.args', json_encode($args));
        }

        return ToolUseDecision::allow($call);
    }

    private function sanitizeArgs(array $args): array {
        $sensitiveKeys = ['password', 'api_key', 'token', 'secret'];
        foreach ($args as $key => $value) {
            if (in_array(strtolower($key), $sensitiveKeys)) {
                $args[$key] = '[REDACTED]';
            }
        }
        return $args;
    }
}
```

### Exporter Configuration

```php
// Factory for common configurations
class OpenTelemetryFactory {
    public static function jaeger(string $endpoint): OpenTelemetryProvider {
        $exporter = new JaegerExporter($endpoint);
        $tracerProvider = new TracerProvider(
            new SimpleSpanProcessor($exporter)
        );

        return new OpenTelemetryProvider(
            tracer: $tracerProvider->getTracer('instructor-php')
        );
    }

    public static function datadog(): OpenTelemetryProvider {
        // Datadog OTLP endpoint
        $exporter = new OtlpHttpExporter('http://localhost:4318');
        // ...
    }

    public static function console(): OpenTelemetryProvider {
        // For development/debugging
        $exporter = new ConsoleSpanExporter();
        // ...
    }
}
```

## Migration Path

1. **Phase 1**: Define semantic attributes for LLM/agent operations
2. **Phase 2**: Create `OpenTelemetryProvider` wrapper
3. **Phase 3**: Implement `OpenTelemetryObserver`
4. **Phase 4**: Add metrics collection
5. **Phase 5**: Add context propagation for subagents
6. **Phase 6**: Create factory helpers for common exporters
7. **Phase 7**: Add sensitive data sanitization

## Success Metrics

- [ ] All agent steps create spans
- [ ] Tool executions traced with latency
- [ ] Token usage recorded as metrics
- [ ] Context propagates to subagents
- [ ] Works with Jaeger, Datadog, etc.
- [ ] Sensitive data not leaked to traces
- [ ] No performance impact when disabled

## Open Questions

1. Should we follow emerging GenAI semantic conventions?
2. How to handle streaming spans (partial updates)?
3. Should traces include message content (privacy)?
4. How to integrate with PHP frameworks (Laravel, Symfony)?
