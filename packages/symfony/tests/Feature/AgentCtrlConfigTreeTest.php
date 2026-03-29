<?php

declare(strict_types=1);

require_once __DIR__.'/../../src/DependencyInjection/Configuration.php';

use Cognesy\Instructor\Symfony\DependencyInjection\Configuration;
use Symfony\Component\Config\Definition\Exception\InvalidConfigurationException;
use Symfony\Component\Config\Definition\Processor;

it('defines a typed agent_ctrl config tree with compatibility aliases', function (): void {
    $processor = new Processor;
    $config = $processor->processConfiguration(new Configuration, [[
        'agent_ctrl' => [
            'enabled' => true,
            'default_agent' => 'claude-code',
            'timeout' => 180,
            'directory' => '/srv/app',
            'sandbox' => 'docker',
            'execution' => [
                'transport' => 'messenger',
                'allow_http' => true,
            ],
            'continuation' => [
                'mode' => 'resume_session',
                'session_key' => 'symfony_agent_ctrl_session',
                'persist_session_id' => true,
                'allow_cross_context_resume' => false,
            ],
            'codex' => [
                'model' => 'codex',
                'sandbox' => 'podman',
            ],
            'backends' => [
                'opencode' => [
                    'enabled' => false,
                    'working_directory' => '/tmp/opencode',
                ],
            ],
        ],
    ]]);

    expect($config['agent_ctrl'])->toHaveKeys([
        'enabled',
        'default_backend',
        'defaults',
        'execution',
        'continuation',
        'backends',
    ]);

    expect($config['agent_ctrl']['enabled'])->toBeTrue()
        ->and($config['agent_ctrl']['default_backend'])->toBe('claude_code')
        ->and($config['agent_ctrl']['defaults'])->toBe([
            'timeout' => 180,
            'working_directory' => '/srv/app',
            'sandbox_driver' => 'docker',
            'model' => null,
        ])
        ->and($config['agent_ctrl']['execution'])->toBe([
            'transport' => 'messenger',
            'allow_http' => true,
            'allow_cli' => true,
            'allow_messenger' => true,
        ])
        ->and($config['agent_ctrl']['continuation'])->toBe([
            'mode' => 'resume_session',
            'session_key' => 'symfony_agent_ctrl_session',
            'persist_session_id' => true,
            'allow_cross_context_resume' => false,
        ])
        ->and($config['agent_ctrl']['backends'])->toBe([
            'opencode' => [
                'enabled' => false,
                'working_directory' => '/tmp/opencode',
                'model' => null,
                'timeout' => null,
                'sandbox_driver' => null,
            ],
            'codex' => [
                'model' => 'codex',
                'sandbox_driver' => 'podman',
                'enabled' => true,
                'timeout' => null,
                'working_directory' => null,
            ],
            'claude_code' => [
                'enabled' => true,
                'model' => null,
                'timeout' => null,
                'working_directory' => null,
                'sandbox_driver' => null,
            ],
            'pi' => [
                'enabled' => true,
                'model' => null,
                'timeout' => null,
                'working_directory' => null,
                'sandbox_driver' => null,
            ],
            'gemini' => [
                'enabled' => true,
                'model' => null,
                'timeout' => null,
                'working_directory' => null,
                'sandbox_driver' => null,
            ],
        ]);
});

it('rejects invalid agent_ctrl transport and timeout values', function (): void {
    $processor = new Processor;

    expect(static fn () => $processor->processConfiguration(new Configuration, [[
        'agent_ctrl' => [
            'execution' => [
                'transport' => 'queue',
            ],
        ],
    ]]))->toThrow(InvalidConfigurationException::class);

    expect(static fn () => $processor->processConfiguration(new Configuration, [[
        'agent_ctrl' => [
            'backends' => [
                'codex' => [
                    'timeout' => 0,
                ],
            ],
        ],
    ]]))->toThrow(InvalidConfigurationException::class);
});
