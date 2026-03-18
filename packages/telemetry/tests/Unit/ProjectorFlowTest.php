<?php declare(strict_types=1);

use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Event\AgentExecutionCompleted as AgentCtrlExecutionCompleted;
use Cognesy\AgentCtrl\Event\AgentExecutionStarted as AgentCtrlExecutionStarted;
use Cognesy\AgentCtrl\Event\RequestBuilt;
use Cognesy\AgentCtrl\Telemetry\AgentCtrlTelemetryProjector;
use Cognesy\AgentCtrl\ValueObject\AgentCtrlExecutionId;
use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Events\AgentStepCompleted;
use Cognesy\Agents\Events\AgentStepStarted;
use Cognesy\Agents\Events\TokenUsageReported;
use Cognesy\Agents\Telemetry\AgentsTelemetryProjector;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptStarted;
use Cognesy\Polyglot\Inference\Events\InferenceAttemptSucceeded;
use Cognesy\Polyglot\Inference\Events\InferenceCompleted;
use Cognesy\Polyglot\Inference\Events\InferenceStarted;
use Cognesy\Polyglot\Inference\Events\InferenceUsageReported;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Adapters\OTel\OtelExporter;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Application\Registry\TraceRegistry;
use Cognesy\Telemetry\Application\Telemetry;
use Cognesy\Telemetry\Application\Exporter\CompositeTelemetryExporter;
use Cognesy\Telemetry\Domain\Trace\TraceContext;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Http\Events\HttpResponseReceived;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated;
use Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted;
use Cognesy\Instructor\Telemetry\InstructorTelemetryProjector;
use Cognesy\Events\Dispatchers\EventDispatcher;

it('projects representative runtime flows into canonical telemetry', function () {
    $otel = new OtelExporter();
    $hub = new Telemetry(new TraceRegistry(), new CompositeTelemetryExporter([$otel]));
    $projector = new CompositeTelemetryProjector([
        new HttpClientTelemetryProjector($hub),
        new PolyglotTelemetryProjector($hub),
        new InstructorTelemetryProjector($hub),
        new AgentsTelemetryProjector($hub),
        new AgentCtrlTelemetryProjector($hub),
    ]);

    $events = new EventDispatcher('telemetry.projector.test');
    (new RuntimeEventBridge($projector))->attachTo($events);

    $context = TraceContext::fresh();
    $events->dispatch(new HttpRequestSent([
        'requestId' => 'http-1',
        'url' => 'https://example.test/health',
        'method' => 'GET',
        'headers' => ['traceparent' => $context->traceparent()],
    ]));
    $events->dispatch(new HttpResponseReceived([
        'requestId' => 'http-1',
        'statusCode' => 200,
    ]));

    $events->dispatch(new InferenceStarted([
        'executionId' => 'inf-1',
        'requestId' => 'req-1',
        'model' => 'gpt-test',
        'isStreamed' => false,
        'messageCount' => 2,
    ]));
    $events->dispatch(new InferenceAttemptStarted('inf-1', 'att-1', 1, 'gpt-test'));
    $events->dispatch(new InferenceAttemptSucceeded([
        'executionId' => 'inf-1',
        'attemptId' => 'att-1',
        'finishReason' => 'stop',
        'durationMs' => 12.5,
        'totalTokens' => 42,
    ]));
    $events->dispatch(new InferenceUsageReported([
        'executionId' => 'inf-1',
        'model' => 'gpt-test',
        'isFinal' => true,
        'inputTokens' => 10,
        'outputTokens' => 32,
        'totalTokens' => 42,
    ]));
    $events->dispatch(new InferenceCompleted([
        'executionId' => 'inf-1',
        'finishReason' => 'stop',
        'attemptCount' => 1,
        'durationMs' => 23.0,
        'totalTokens' => 42,
    ]));

    $events->dispatch(new StructuredOutputStarted([
        'executionId' => 'so-1',
        'requestId' => 'so-req-1',
        'model' => 'gpt-struct',
        'messageCount' => 1,
        'isStreamed' => false,
    ]));
    $events->dispatch(new StructuredOutputResponseGenerated([
        'executionId' => 'so-1',
        'phase' => 'response.generated',
        'valueType' => 'stdClass',
        'hasValue' => true,
        'finishReason' => 'stop',
        'totalTokens' => 12,
    ]));

    $usage = new InferenceUsage(5, 7);
    $events->dispatch(new AgentExecutionStarted('agent-1', 'exec-1', null, 1, 2));
    $events->dispatch(new AgentStepStarted('agent-1', 'exec-1', null, 1, 1, 2));
    $events->dispatch(new AgentStepCompleted(
        agentId: 'agent-1',
        executionId: 'exec-1',
        parentAgentId: null,
        stepNumber: 1,
        hasToolCalls: false,
        errorCount: 0,
        errorMessages: '',
        usage: $usage,
        finishReason: null,
        startedAt: new DateTimeImmutable('-1 second'),
        durationMs: 15.0,
    ));
    $events->dispatch(new TokenUsageReported('agent-1', 'exec-1', null, 'step', $usage));
    $events->dispatch(new AgentExecutionCompleted('agent-1', 'exec-1', null, \Cognesy\Agents\Enums\ExecutionStatus::Completed, 1, $usage, null));

    $agentCtrlExecutionId = AgentCtrlExecutionId::fromString('agent-ctrl-exec-1');
    $events->dispatch(new AgentCtrlExecutionStarted(AgentType::Codex, $agentCtrlExecutionId, 'Inspect repo', 'gpt-codex', '/tmp/work'));
    $events->dispatch(new RequestBuilt(AgentType::Codex, $agentCtrlExecutionId, 'CodexRequest', 2.5));
    $events->dispatch(new AgentCtrlExecutionCompleted(AgentType::Codex, $agentCtrlExecutionId, 0, 0));

    expect($otel->observations())->toHaveCount(8);
    expect(array_map(fn($observation) => $observation->name(), $otel->observations()))
        ->toContain('http.client.request', 'llm.inference', 'agent.execute', 'agent_ctrl.RequestBuilt', 'agent_ctrl.execute');
});
