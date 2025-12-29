<?php

declare(strict_types=1);

namespace Cognesy\Instructor\Laravel\Facades;

use Cognesy\AgentCtrl\AgentCtrl as BaseAgentCtrl;
use Cognesy\AgentCtrl\Builder\ClaudeCodeBridgeBuilder;
use Cognesy\AgentCtrl\Builder\CodexBridgeBuilder;
use Cognesy\AgentCtrl\Builder\OpenCodeBridgeBuilder;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\Instructor\Laravel\Testing\AgentCtrlFake;
use Illuminate\Support\Facades\Facade;

/**
 * Laravel Facade for CLI-based code agents.
 *
 * Provides a unified interface for invoking code agents:
 * - Claude Code (Anthropic)
 * - Codex (OpenAI)
 * - OpenCode
 *
 * Usage:
 *   $response = AgentCtrl::claudeCode()->execute('Generate a migration');
 *   $response = AgentCtrl::codex()->execute('Refactor this function');
 *   $response = AgentCtrl::openCode()->execute('Write tests');
 *
 * Testing:
 *   AgentCtrl::fake(['Generated migration file...']);
 *   $response = AgentCtrl::claudeCode()->execute('...');
 *   AgentCtrl::assertExecuted();
 *
 * @method static ClaudeCodeBridgeBuilder claudeCode()
 * @method static CodexBridgeBuilder codex()
 * @method static OpenCodeBridgeBuilder openCode()
 * @method static ClaudeCodeBridgeBuilder|CodexBridgeBuilder|OpenCodeBridgeBuilder make(AgentType $type)
 *
 * @see BaseAgentCtrl
 */
class AgentCtrl extends Facade
{
    /**
     * Replace the bound instance with a fake for testing.
     *
     * @param  array<string>  $responses  Array of text responses to return
     */
    public static function fake(array $responses = []): AgentCtrlFake
    {
        $fake = new AgentCtrlFake($responses);
        static::swap($fake);

        return $fake;
    }

    /**
     * Get a Claude Code agent builder with Laravel defaults.
     */
    public static function claudeCode(): ClaudeCodeBridgeBuilder
    {
        $builder = BaseAgentCtrl::claudeCode();

        return static::applyLaravelDefaults($builder, 'claude_code');
    }

    /**
     * Get a Codex agent builder with Laravel defaults.
     */
    public static function codex(): CodexBridgeBuilder
    {
        $builder = BaseAgentCtrl::codex();

        return static::applyLaravelDefaults($builder, 'codex');
    }

    /**
     * Get an OpenCode agent builder with Laravel defaults.
     */
    public static function openCode(): OpenCodeBridgeBuilder
    {
        $builder = BaseAgentCtrl::openCode();

        return static::applyLaravelDefaults($builder, 'opencode');
    }

    /**
     * Get an agent builder by type.
     */
    public static function make(AgentType $type): ClaudeCodeBridgeBuilder|CodexBridgeBuilder|OpenCodeBridgeBuilder
    {
        return match ($type) {
            AgentType::ClaudeCode => static::claudeCode(),
            AgentType::Codex => static::codex(),
            AgentType::OpenCode => static::openCode(),
        };
    }

    /**
     * Apply Laravel configuration defaults to an agent builder.
     *
     * @template T of ClaudeCodeBridgeBuilder|CodexBridgeBuilder|OpenCodeBridgeBuilder
     *
     * @param  T  $builder
     * @return T
     */
    protected static function applyLaravelDefaults(
        ClaudeCodeBridgeBuilder|CodexBridgeBuilder|OpenCodeBridgeBuilder $builder,
        string $agentKey
    ): ClaudeCodeBridgeBuilder|CodexBridgeBuilder|OpenCodeBridgeBuilder {
        $config = config("instructor.agents.{$agentKey}", []);

        // Apply model if configured
        if ($model = $config['model'] ?? null) {
            $builder->withModel($model);
        }

        // Apply timeout if configured
        if ($timeout = $config['timeout'] ?? config('instructor.agents.timeout')) {
            $builder->withTimeout($timeout);
        }

        // Apply working directory if configured
        if ($directory = $config['directory'] ?? config('instructor.agents.directory')) {
            $builder->inDirectory($directory);
        }

        // Apply sandbox driver if configured
        if ($sandbox = $config['sandbox'] ?? config('instructor.agents.sandbox')) {
            $sandboxDriver = \Cognesy\AgentCtrl\Common\Enum\SandboxDriver::from($sandbox);
            $builder->withSandboxDriver($sandboxDriver);
        }

        return $builder;
    }

    /**
     * Get the facade accessor (not used as we use static methods).
     */
    protected static function getFacadeAccessor(): string
    {
        return BaseAgentCtrl::class;
    }
}
