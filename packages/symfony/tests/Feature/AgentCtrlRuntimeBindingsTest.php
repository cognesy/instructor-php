<?php

declare(strict_types=1);

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

use Cognesy\Instructor\Symfony\AgentCtrl\AgentCtrlExecutionContext;
use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntime;
use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes;
use Symfony\Component\DependencyInjection\ContainerBuilder;

it('registers context-specific AgentCtrl runtime adapters', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'agent_ctrl' => [
            'enabled' => true,
            'execution' => [
                'transport' => 'messenger',
                'allow_http' => true,
                'allow_messenger' => true,
            ],
        ],
    ]], $container);

    $container->compile();

    $registry = $container->get(SymfonyAgentCtrlRuntimes::class);
    $cliRuntime = $container->get('instructor.agent_ctrl.runtime.cli');
    $httpRuntime = $container->get('instructor.agent_ctrl.runtime.http');
    $messengerRuntime = $container->get('instructor.agent_ctrl.runtime.messenger');

    expect($registry)->toBeInstanceOf(SymfonyAgentCtrlRuntimes::class);
    expect($cliRuntime)->toBeInstanceOf(SymfonyAgentCtrlRuntime::class);
    expect($httpRuntime)->toBeInstanceOf(SymfonyAgentCtrlRuntime::class);
    expect($messengerRuntime)->toBeInstanceOf(SymfonyAgentCtrlRuntime::class);
    expect($cliRuntime->context())->toBe(AgentCtrlExecutionContext::Cli);
    expect($cliRuntime->policy()->requiresMessengerDispatch())->toBeTrue();
    expect($cliRuntime->policy()->allowsInlineExecution())->toBeFalse();
    expect($httpRuntime->context())->toBe(AgentCtrlExecutionContext::Http);
    expect($httpRuntime->policy()->requiresMessengerDispatch())->toBeTrue();
    expect($httpRuntime->policy()->allowsInlineExecution())->toBeFalse();
    expect($messengerRuntime->context())->toBe(AgentCtrlExecutionContext::Messenger);
    expect($messengerRuntime->policy()->allowsInlineExecution())->toBeTrue();
});

it('rejects disabled http AgentCtrl runtime access', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'agent_ctrl' => [
            'enabled' => true,
            'execution' => [
                'allow_http' => false,
            ],
        ],
    ]], $container);

    $container->compile();

    /** @var SymfonyAgentCtrlRuntime $httpRuntime */
    $httpRuntime = $container->get('instructor.agent_ctrl.runtime.http');

    expect(static fn () => $httpRuntime->defaultBuilder())
        ->toThrow(RuntimeException::class, 'AgentCtrl http execution is disabled.');
});

it('rejects inline http execution when messenger transport is configured', function (): void {
    $container = new ContainerBuilder;
    $extension = new \Cognesy\Instructor\Symfony\DependencyInjection\InstructorSymfonyExtension;

    $extension->load([[
        'agent_ctrl' => [
            'enabled' => true,
            'execution' => [
                'transport' => 'messenger',
                'allow_http' => true,
            ],
        ],
    ]], $container);

    $container->compile();

    /** @var SymfonyAgentCtrlRuntime $httpRuntime */
    $httpRuntime = $container->get('instructor.agent_ctrl.runtime.http');

    expect(static fn () => $httpRuntime->execute('Generate a diff.'))
        ->toThrow(RuntimeException::class, 'AgentCtrl http execution is configured for messenger transport.');
});
