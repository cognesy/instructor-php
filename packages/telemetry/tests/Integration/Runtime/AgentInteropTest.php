<?php declare(strict_types=1);

use Cognesy\AgentCtrl\AgentCtrl;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\AgentCtrl\Telemetry\AgentCtrlTelemetryProjector;
use Cognesy\Agents\Builder\AgentBuilder;
use Cognesy\Agents\Capability\Bash\UseBash;
use Cognesy\Agents\Capability\Core\UseContextConfig;
use Cognesy\Agents\Capability\Core\UseGuards;
use Cognesy\Agents\Capability\Core\UseLLMConfig;
use Cognesy\Agents\Data\AgentState;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Telemetry\AgentsTelemetryProjector;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Telemetry\HttpClientTelemetryProjector;
use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\LLMProvider;
use Cognesy\Polyglot\Telemetry\PolyglotTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\CompositeTelemetryProjector;
use Cognesy\Telemetry\Application\Projector\RuntimeEventBridge;
use Cognesy\Telemetry\Tests\Integration\Support\InteropEnv;
use Cognesy\Telemetry\Tests\Integration\Support\InteropRun;
use Cognesy\Telemetry\Tests\Integration\Support\InteropTelemetryFactory;
use Cognesy\Telemetry\Tests\Integration\Support\LangfuseQueryClient;
use Cognesy\Telemetry\Tests\Integration\Support\LogfireQueryClient;
use Cognesy\Telemetry\Tests\Integration\Support\Polling;

it('exports agent runtime telemetry to logfire and can query it back', function () {
    InteropEnv::requireLogfire();
    InteropEnv::requireOpenAi();

    $run = InteropRun::fresh('agent-logfire');
    $serviceName = $run->serviceName('tests.telemetry.agent.logfire');
    $events = new EventDispatcher($serviceName);
    $hub = InteropTelemetryFactory::logfire($serviceName);
    $client = LogfireQueryClient::fromEnv();

    (new RuntimeEventBridge(new CompositeTelemetryProjector([
        new AgentsTelemetryProjector($hub),
        new PolyglotTelemetryProjector($hub),
        new HttpClientTelemetryProjector($hub),
    ])))->attachTo($events);

    $agent = AgentBuilder::base($events)
        ->withCapability(new UseContextConfig(systemPrompt: <<<'SYSTEM'
            Use bash exactly once before answering.
            Run only: pwd
            Do not run any other command.
            After the tool returns, answer exactly as requested.
            SYSTEM))
        ->withCapability(new UseLLMConfig(llm: LLMProvider::using('openai')))
        ->withCapability(new UseBash(baseDir: getcwd() ?: '.'))
        ->withCapability(new UseGuards(maxSteps: 4, maxTokens: 4096, maxExecutionTime: 30))
        ->build();

    $expected = 'ACK ' . $run->id();
    $finalState = $agent->execute(
        AgentState::empty()->withMessages(Messages::fromString("Use bash exactly once and then reply with exactly: {$expected}")),
    );
    $hub->flush();

    $toolCallCount = array_sum(array_map(
        static fn($stepExecution): int => count($stepExecution->step()->toolExecutions()->all()),
        $finalState->stepExecutions()->all(),
    ));
    $timestamp = Polling::eventually(
        probe: static fn(): ?string => $client->latestTimestampForService($serviceName),
    );

    expect($finalState->status())->toBe(ExecutionStatus::Completed)
        ->and($finalState->finalResponse()->toString())->toContain($expected)
        ->and($toolCallCount)->toBeGreaterThan(0)
        ->and($timestamp)->not->toBeNull();
})->group('integration', 'interop', 'runtime', 'agent', 'logfire');

it('exports agent runtime telemetry to langfuse and can query it back', function () {
    InteropEnv::requireLangfuse();
    InteropEnv::requireOpenAi();

    $run = InteropRun::fresh('agent-langfuse');
    $prompt = 'Use bash exactly once and then reply with exactly: ACK ' . $run->id();
    $events = new EventDispatcher('tests.telemetry.agent.langfuse');
    $hub = InteropTelemetryFactory::langfuse();
    $client = LangfuseQueryClient::fromEnv();

    (new RuntimeEventBridge(new CompositeTelemetryProjector([
        new AgentsTelemetryProjector($hub),
        new PolyglotTelemetryProjector($hub),
        new HttpClientTelemetryProjector($hub),
    ])))->attachTo($events);

    $agent = AgentBuilder::base($events)
        ->withCapability(new UseContextConfig(systemPrompt: <<<'SYSTEM'
            Use bash exactly once before answering.
            Run only: pwd
            Do not run any other command.
            After the tool returns, answer exactly as requested.
            SYSTEM))
        ->withCapability(new UseLLMConfig(llm: LLMProvider::using('openai')))
        ->withCapability(new UseBash(baseDir: getcwd() ?: '.'))
        ->withCapability(new UseGuards(maxSteps: 4, maxTokens: 4096, maxExecutionTime: 30))
        ->build();

    $finalState = $agent->execute(
        AgentState::empty()->withMessages(Messages::fromString($prompt)),
    );
    $hub->flush();

    $toolCallCount = array_sum(array_map(
        static fn($stepExecution): int => count($stepExecution->step()->toolExecutions()->all()),
        $finalState->stepExecutions()->all(),
    ));
    $trace = Polling::eventually(
        probe: static fn(): ?array => $client->latestTraceMatching($prompt),
        timeoutSeconds: 60,
    );

    expect($finalState->status())->toBe(ExecutionStatus::Completed)
        ->and($finalState->finalResponse()->toString())->toContain('ACK ' . $run->id())
        ->and($toolCallCount)->toBeGreaterThan(0)
        ->and($trace)->not->toBeNull();
})->group('integration', 'interop', 'runtime', 'agent', 'langfuse');

it('exports agentctrl telemetry to logfire and can query it back', function () {
    InteropEnv::requireLogfire();
    InteropEnv::requireCodexBinary();

    $run = InteropRun::fresh('agentctrl-logfire');
    $serviceName = $run->serviceName('tests.telemetry.agentctrl.logfire');
    $hub = InteropTelemetryFactory::logfire($serviceName);
    $bridge = new RuntimeEventBridge(new AgentCtrlTelemetryProjector($hub));
    $client = LogfireQueryClient::fromEnv();
    $prompt = 'Use bash exactly once to run pwd. Then reply with exactly: ACK ' . $run->id();

    $response = AgentCtrl::codex()
        ->wiretap($bridge->handle(...))
        ->withSandbox(SandboxMode::ReadOnly)
        ->inDirectory(getcwd() ?: '.')
        ->executeStreaming($prompt);
    $hub->flush();

    $timestamp = Polling::eventually(
        probe: static fn(): ?string => $client->latestTimestampForService($serviceName),
    );

    expect($response->isSuccess())->toBeTrue()
        ->and($response->text())->toContain('ACK ' . $run->id())
        ->and(count($response->toolCalls))->toBeGreaterThan(0)
        ->and($timestamp)->not->toBeNull();
})->group('integration', 'interop', 'runtime', 'agentctrl', 'logfire');

it('exports agentctrl telemetry to langfuse and can query it back', function () {
    InteropEnv::requireLangfuse();
    InteropEnv::requireCodexBinary();

    $run = InteropRun::fresh('agentctrl-langfuse');
    $hub = InteropTelemetryFactory::langfuse();
    $bridge = new RuntimeEventBridge(new AgentCtrlTelemetryProjector($hub));
    $client = LangfuseQueryClient::fromEnv();
    $prompt = 'Use bash exactly once to run pwd. Then reply with exactly: ACK ' . $run->id();

    $response = AgentCtrl::codex()
        ->wiretap($bridge->handle(...))
        ->withSandbox(SandboxMode::ReadOnly)
        ->inDirectory(getcwd() ?: '.')
        ->executeStreaming($prompt);
    $hub->flush();

    $trace = Polling::eventually(
        probe: static fn(): ?array => $client->latestTraceMatching($prompt),
        timeoutSeconds: 60,
    );

    expect($response->isSuccess())->toBeTrue()
        ->and($response->text())->toContain('ACK ' . $run->id())
        ->and(count($response->toolCalls))->toBeGreaterThan(0)
        ->and($trace)->not->toBeNull();
})->group('integration', 'interop', 'runtime', 'agentctrl', 'langfuse');
