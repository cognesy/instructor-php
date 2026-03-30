<?php

declare(strict_types=1);

use Cognesy\AgentCtrl\Builder\ClaudeCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\Instructor\Symfony\AgentCtrl\SymfonyAgentCtrl;
use Cognesy\Instructor\Symfony\Tests\Support\SymfonyTestApp;
use Cognesy\Sandbox\Enums\SandboxDriver;

it('registers Symfony AgentCtrl services and backend entrypoints', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $manager = $app->service(SymfonyAgentCtrl::class);
            $defaultBuilder = $app->service('instructor.agent_ctrl.builder.default');
            $claudeBuilder = $app->service(ClaudeCodeBridgeBuilder::class);
            $codexBuilder = $app->service('instructor.agent_ctrl.builder.codex');

            expect($manager)->toBeInstanceOf(SymfonyAgentCtrl::class);
            expect($defaultBuilder)->toBeInstanceOf(CodexBridgeBuilder::class);
            expect($claudeBuilder)->toBeInstanceOf(ClaudeCodeBridgeBuilder::class);
            expect($codexBuilder)->toBeInstanceOf(CodexBridgeBuilder::class);
            expect(readProperty($claudeBuilder, 'model'))->toBe('claude-sonnet-4-20250514');
            expect(readProperty($claudeBuilder, 'timeout'))->toBe(300);
            expect(readProperty($claudeBuilder, 'workingDirectory'))->toBe('/srv/app');
            expect(readProperty($claudeBuilder, 'sandboxDriver'))->toBe(SandboxDriver::Docker);
            expect(readProperty($codexBuilder, 'model'))->toBe('codex');
            expect(readProperty($codexBuilder, 'timeout'))->toBe(300);
            expect(readProperty($codexBuilder, 'workingDirectory'))->toBe('/srv/app');
            expect(readProperty($codexBuilder, 'sandboxDriver'))->toBe(SandboxDriver::Docker);
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => true,
            'default_backend' => 'codex',
            'defaults' => [
                'timeout' => 300,
                'working_directory' => '/srv/app',
                'sandbox_driver' => 'docker',
            ],
            'backends' => [
                'claude_code' => [
                    'model' => 'claude-sonnet-4-20250514',
                ],
                'codex' => [
                    'model' => 'codex',
                ],
            ],
        ],
    ]);
});

it('rejects AgentCtrl builder access when the Symfony integration is disabled', function (): void {
    SymfonyTestApp::using(
        callback: static function (SymfonyTestApp $app): void {
            $manager = $app->service(SymfonyAgentCtrl::class);

            try {
                $manager->codex();
                test()->fail('Expected AgentCtrl builder access to be rejected when the integration is disabled.');
            } catch (RuntimeException $exception) {
                expect($exception->getMessage())->toContain('AgentCtrl integration is disabled.');
            }
        },
        instructorConfig: [
        'agent_ctrl' => [
            'enabled' => false,
        ],
    ]);
});

function readProperty(object $object, string $property): mixed
{
    $reflection = new ReflectionProperty($object, $property);

    return $reflection->getValue($object);
}
