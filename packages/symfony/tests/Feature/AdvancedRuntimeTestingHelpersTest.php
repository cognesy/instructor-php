<?php

declare(strict_types=1);

use Cognesy\Agents\Events\AgentExecutionCompleted;
use Cognesy\Agents\Events\AgentExecutionStarted;
use Cognesy\Agents\Enums\ExecutionStatus;
use Cognesy\Agents\Template\Data\AgentDefinition;
use Cognesy\Events\Contracts\CanHandleEvents;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessage;
use Cognesy\Instructor\Symfony\Delivery\Messenger\ExecuteNativeAgentPromptMessageHandler;
use Cognesy\Instructor\Symfony\Tests\Support\RecordingTelemetryExporter;
use Cognesy\Instructor\Symfony\Tests\Support\ScriptedAgentLoopFactory;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyNativeAgentOverrides;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTelemetryServiceOverrides;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Cognesy\Polyglot\Inference\Data\InferenceUsage;

it('provides native-agent override helpers for scripted execution in tests', function (): void {
    $loopFactory = ScriptedAgentLoopFactory::fromResponses('helper-response');

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($loopFactory): void {
            $handler = $app->service(ExecuteNativeAgentPromptMessageHandler::class);
            $session = $handler(new ExecuteNativeAgentPromptMessage(
                definition: 'helper-agent',
                prompt: 'Use the helper',
            ));

            $messages = $session->state()->messages()->toArray();

            expect($messages)->toHaveCount(2)
                ->and($messages[1]['content'] ?? null)->toBe('helper-response')
                ->and($loopFactory->recorded())->toHaveCount(1);
        },
        instructorConfig: advancedRuntimeTestConfig(),
        containerConfigurators: [
            SymfonyNativeAgentOverrides::definition(new AgentDefinition(
                name: 'helper-agent',
                description: 'Helper agent',
                systemPrompt: 'Be explicit',
            )),
            SymfonyNativeAgentOverrides::loopFactory($loopFactory),
        ],
    );
});

it('provides telemetry exporter overrides through the shared service seam', function (): void {
    $exporter = new RecordingTelemetryExporter();

    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app) use ($exporter): void {
            $events = $app->service(CanHandleEvents::class);

            $events->dispatch(new AgentExecutionStarted(
                agentId: 'agent-helper',
                executionId: 'exec-helper',
                parentAgentId: null,
                messageCount: 1,
                availableTools: 0,
            ));
            $events->dispatch(new AgentExecutionCompleted(
                agentId: 'agent-helper',
                executionId: 'exec-helper',
                parentAgentId: null,
                status: ExecutionStatus::Completed,
                totalSteps: 1,
                totalUsage: InferenceUsage::none(),
                errors: null,
            ));

            expect($exporter->observations)->toHaveCount(1);
        },
        instructorConfig: advancedRuntimeTestConfig([
            'telemetry' => [
                'enabled' => true,
                'driver' => 'otel',
                'drivers' => [
                    'otel' => [
                        'endpoint' => 'https://otel.example.invalid',
                    ],
                ],
                'projectors' => [
                    'instructor' => false,
                    'polyglot' => false,
                    'http' => false,
                    'agent_ctrl' => false,
                    'agents' => true,
                ],
            ],
        ]),
        containerConfigurators: [
            SymfonyTelemetryServiceOverrides::exporter($exporter),
        ],
    );
});

/**
 * @param array<string, mixed> $overrides
 * @return array<string, mixed>
 */
function advancedRuntimeTestConfig(array $overrides = []): array
{
    return array_replace_recursive([
        'connections' => [
            'openai' => [
                'driver' => 'openai',
                'api_key' => 'test-key',
                'model' => 'gpt-4o-mini',
            ],
        ],
    ], $overrides);
}
