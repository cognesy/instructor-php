<?php

declare(strict_types=1);

require_once __DIR__.'/../../src/AgentCtrl/AgentCtrlContinuationMode.php';
require_once __DIR__.'/../../src/AgentCtrl/AgentCtrlContinuationPolicy.php';
require_once __DIR__.'/../../src/AgentCtrl/AgentCtrlContinuationReference.php';
require_once __DIR__.'/../../src/AgentCtrl/AgentCtrlExecutionContext.php';
require_once __DIR__.'/../../src/AgentCtrl/AgentCtrlExecutionPolicy.php';
require_once __DIR__.'/../../src/AgentCtrl/SymfonyAgentCtrl.php';
require_once __DIR__.'/../../src/AgentCtrl/SymfonyAgentCtrlRuntime.php';
require_once __DIR__.'/../../src/AgentCtrl/SymfonyAgentCtrlRuntimes.php';
require_once __DIR__.'/../../src/Support/SymfonyConfigProvider.php';
require_once __DIR__.'/../../src/Support/SymfonyEventBusFactory.php';
require_once __DIR__.'/../../src/Support/SymfonyHttpTransportFactory.php';
require_once __DIR__.'/../../src/DependencyInjection/InstructorSymfonyExtension.php';
require_once __DIR__.'/../../src/DependencyInjection/Configuration.php';

use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Dto\AgentResponse;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Instructor\Symfony\AgentCtrl\AgentCtrlContinuationMode;
use Cognesy\Instructor\Symfony\AgentCtrl\AgentCtrlContinuationReference;
use Cognesy\Instructor\Symfony\AgentCtrl\AgentCtrlExecutionContext;
use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntime;
use Symfony\Component\DependencyInjection\ContainerBuilder;

it('creates default continuation references and handoff tokens from runtime policy', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
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
    ]], $container);

    $container->compile();

    /** @var SymfonyAgentCtrlRuntime $httpRuntime */
    $httpRuntime = $container->get('instructor.agent_ctrl.runtime.http');
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
});

it('rejects cross-context resume when continuation policy forbids it', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
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
    ]], $container);

    $container->compile();

    /** @var SymfonyAgentCtrlRuntime $httpRuntime */
    $httpRuntime = $container->get('instructor.agent_ctrl.runtime.http');
    /** @var SymfonyAgentCtrlRuntime $messengerRuntime */
    $messengerRuntime = $container->get('instructor.agent_ctrl.runtime.messenger');

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
});

it('rejects cross-context continue-last handoff even when cross-context resume is enabled', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
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
    ]], $container);

    $container->compile();

    /** @var SymfonyAgentCtrlRuntime $messengerRuntime */
    $messengerRuntime = $container->get('instructor.agent_ctrl.runtime.messenger');
    $continueLast = AgentCtrlContinuationReference::continueLast(
        backend: 'codex',
        sessionKey: 'agent_ctrl_session_id',
        sourceContext: AgentCtrlExecutionContext::Http,
    );

    expect(static fn () => $messengerRuntime->resumeSession($continueLast))
        ->toThrow(RuntimeException::class, 'AgentCtrl continue_last continuation from http cannot be resumed in messenger');
});

it('resumes specific sessions and continue-last flows through the Symfony runtime seam', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
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
    ]], $container);

    $container->compile();

    /** @var SymfonyAgentCtrlRuntime $messengerRuntime */
    $messengerRuntime = $container->get('instructor.agent_ctrl.runtime.messenger');
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
