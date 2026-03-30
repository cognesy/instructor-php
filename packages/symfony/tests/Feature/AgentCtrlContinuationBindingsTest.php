<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Instructor\Symfony\AgentCtrl\AgentCtrlContinuationMode;
use Cognesy\Instructor\Symfony\AgentCtrl\AgentCtrlContinuationReference;
use Cognesy\Instructor\Symfony\AgentCtrl\AgentCtrlExecutionContext;
use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntime;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;

it('creates default continuation references and handoff tokens from runtime policy', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            /** @var SymfonyAgentCtrlRuntime $httpRuntime */
            $httpRuntime = $app->service('instructor.agent_ctrl.runtime.http');
            $defaultContinuation = $httpRuntime->continuation();
            $handoff = $httpRuntime->handoff(new AgentResponse(
                agentType: AgentType::Codex,
                text: 'Done',
                exitCode: 0,
                executionId: 'exec-http-1',
                sessionId: 'thread_http_1',
            ));

            expect($defaultContinuation)->toBeInstanceOf(AgentCtrlContinuationReference::class)
                ->and($defaultContinuation->backend)->toBe('codex')
                ->and($defaultContinuation->mode)->toBe(AgentCtrlContinuationMode::ContinueLast)
                ->and($defaultContinuation->sessionKey)->toBe('symfony_agent_ctrl_session')
                ->and($defaultContinuation->sourceContext)->toBe(AgentCtrlExecutionContext::Http)
                ->and($handoff)->toBeInstanceOf(AgentCtrlContinuationReference::class)
                ->and($handoff?->mode)->toBe(AgentCtrlContinuationMode::ResumeSession)
                ->and($handoff?->sessionId)->toBe('thread_http_1')
                ->and($handoff?->sourceContext)->toBe(AgentCtrlExecutionContext::Http)
                ->and(AgentCtrlContinuationReference::fromArray($handoff?->toArray() ?? []))->toEqual($handoff);
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => true,
            'default_backend' => 'codex',
            'execution' => [
                'allow_http' => true,
            ],
            'continuation' => [
                'mode' => 'continue_last',
                'session_key' => 'symfony_agent_ctrl_session',
                'persist_session_id' => true,
                'allow_cross_context_resume' => true,
            ],
        ],
    ]);
});

it('rejects cross-context resume when continuation policy forbids it', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            /** @var SymfonyAgentCtrlRuntime $httpRuntime */
            $httpRuntime = $app->service('instructor.agent_ctrl.runtime.http');
            /** @var SymfonyAgentCtrlRuntime $messengerRuntime */
            $messengerRuntime = $app->service('instructor.agent_ctrl.runtime.messenger');

            $handoff = $httpRuntime->handoff(new AgentResponse(
                agentType: AgentType::Codex,
                text: 'Done',
                exitCode: 0,
                executionId: 'exec-http-2',
                sessionId: 'thread_http_2',
            ));
            $continueLast = AgentCtrlContinuationReference::continueLast(
                backend: 'codex',
                sessionKey: 'agent_ctrl_session_id',
                sourceContext: AgentCtrlExecutionContext::Http,
            );

            expect(static fn () => $messengerRuntime->resumeSession($handoff ?? 'thread_http_2'))
                ->toThrow(RuntimeException::class, 'AgentCtrl continuation from http cannot be resumed in messenger');
            expect(static fn () => $messengerRuntime->resumeSession($continueLast))
                ->toThrow(RuntimeException::class, 'AgentCtrl continuation from http cannot be resumed in messenger');
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => true,
            'default_backend' => 'codex',
            'execution' => [
                'allow_http' => true,
                'allow_messenger' => true,
            ],
            'continuation' => [
                'persist_session_id' => true,
                'allow_cross_context_resume' => false,
            ],
        ],
    ]);
});

it('rejects cross-context continue-last handoff even when cross-context resume is enabled', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            /** @var SymfonyAgentCtrlRuntime $messengerRuntime */
            $messengerRuntime = $app->service('instructor.agent_ctrl.runtime.messenger');
            $continueLast = AgentCtrlContinuationReference::continueLast(
                backend: 'codex',
                sessionKey: 'agent_ctrl_session_id',
                sourceContext: AgentCtrlExecutionContext::Http,
            );

            expect(static fn () => $messengerRuntime->resumeSession($continueLast))
                ->toThrow(RuntimeException::class, 'AgentCtrl continue_last continuation from http cannot be resumed in messenger');
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => true,
            'default_backend' => 'codex',
            'execution' => [
                'allow_http' => true,
                'allow_messenger' => true,
            ],
            'continuation' => [
                'allow_cross_context_resume' => true,
            ],
        ],
    ]);
});

it('resumes specific sessions and continue-last flows through the Symfony runtime seam', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            /** @var SymfonyAgentCtrlRuntime $messengerRuntime */
            $messengerRuntime = $app->service('instructor.agent_ctrl.runtime.messenger');
            $reference = AgentCtrlContinuationReference::resumeSession(
                backend: 'codex',
                sessionId: 'thread_messenger_1',
                sessionKey: 'agent_ctrl_session_id',
                sourceContext: AgentCtrlExecutionContext::Http,
            );

            $continued = $messengerRuntime->continueLast();
            $resumed = $messengerRuntime->resumeSession($reference);

            expect($continued)->toBeInstanceOf(CodexBridgeBuilder::class)
                ->and(readContinuationProperty($continued, 'resumeLast'))->toBeTrue()
                ->and($resumed)->toBeInstanceOf(CodexBridgeBuilder::class)
                ->and(readContinuationProperty($resumed, 'resumeSessionId'))->toBe('thread_messenger_1');
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => true,
            'default_backend' => 'codex',
            'execution' => [
                'allow_messenger' => true,
            ],
            'continuation' => [
                'allow_cross_context_resume' => true,
            ],
        ],
    ]);
});

it('rejects malformed serialized continuation references', function (): void {
    expect(static fn () => AgentCtrlContinuationReference::fromArray([]))
        ->toThrow(\InvalidArgumentException::class, 'AgentCtrl continuation reference is missing required "backend".');

    expect(static fn () => AgentCtrlContinuationReference::fromArray([
        'backend' => 'codex',
        'mode' => 'unsupported',
        'session_key' => 'agent_ctrl_session_id',
    ]))->toThrow(\InvalidArgumentException::class, 'AgentCtrl continuation reference has invalid "mode": unsupported.');

    expect(static fn () => AgentCtrlContinuationReference::fromArray([
        'backend' => 'codex',
        'mode' => 'continue_last',
        'session_key' => 'agent_ctrl_session_id',
        'source_context' => 'queue',
    ]))->toThrow(\InvalidArgumentException::class, 'AgentCtrl continuation reference has invalid "source_context": queue.');
});

function readContinuationProperty(object $object, string $property): mixed
{
    $reflection = new ReflectionProperty($object, $property);

    return $reflection->getValue($object);
}
