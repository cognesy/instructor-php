<?php

declare(strict_types=1);

use Cognesy\Instructor\Symfony\AgentCtrl\AgentCtrlExecutionContext;
use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntime;
use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrlRuntimes;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;

it('registers context-specific AgentCtrl runtime adapters', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $registry = $app->service(SymfonyAgentCtrlRuntimes::class);
            $cliRuntime = $app->service('instructor.agent_ctrl.runtime.cli');
            $httpRuntime = $app->service('instructor.agent_ctrl.runtime.http');
            $messengerRuntime = $app->service('instructor.agent_ctrl.runtime.messenger');

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
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => true,
            'execution' => [
                'transport' => 'messenger',
                'allow_http' => true,
                'allow_messenger' => true,
            ],
        ],
    ]);
});

it('rejects disabled http AgentCtrl runtime access', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            /** @var SymfonyAgentCtrlRuntime $httpRuntime */
            $httpRuntime = $app->service('instructor.agent_ctrl.runtime.http');

            expect(static fn () => $httpRuntime->defaultBuilder())
                ->toThrow(RuntimeException::class, 'AgentCtrl http execution is disabled.');
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => true,
            'execution' => [
                'allow_http' => false,
            ],
        ],
    ]);
});

it('rejects inline http execution when messenger transport is configured', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            /** @var SymfonyAgentCtrlRuntime $httpRuntime */
            $httpRuntime = $app->service('instructor.agent_ctrl.runtime.http');

            expect(static fn () => $httpRuntime->execute('Generate a diff.'))
                ->toThrow(RuntimeException::class, 'AgentCtrl http execution is configured for messenger transport.');
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => true,
            'execution' => [
                'transport' => 'messenger',
                'allow_http' => true,
            ],
        ],
    ]);
});
