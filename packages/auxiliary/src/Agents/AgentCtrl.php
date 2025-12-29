<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents;

use Cognesy\Auxiliary\Agents\Builder\ClaudeCodeBridgeBuilder;
use Cognesy\Auxiliary\Agents\Builder\CodexBridgeBuilder;
use Cognesy\Auxiliary\Agents\Builder\OpenCodeBridgeBuilder;
use Cognesy\Auxiliary\Agents\Contract\AgentBridgeBuilder;
use Cognesy\Auxiliary\Agents\Enum\AgentType;

/**
 * Entry point for the unified agent bridge abstraction.
 *
 * Provides a fluent API to configure and execute prompts against
 * any supported CLI-based code agent (Claude Code, Codex, OpenCode).
 *
 * @example Basic usage
 * ```php
 * $response = AgentCtrl::make(AgentType::ClaudeCode)
 *     ->execute('What files are in this directory?');
 * ```
 *
 * @example With configuration
 * ```php
 * $response = AgentCtrl::make(AgentType::OpenCode)
 *     ->withModel('anthropic/claude-sonnet-4-5')
 *     ->withAgent('coder')
 *     ->onText(fn($text) => print($text))
 *     ->executeStreaming('Refactor the User model');
 * ```
 *
 * @example Runtime switching
 * ```php
 * $agentType = AgentType::from($config['agent']);
 * $response = AgentCtrl::make($agentType)
 *     ->withModel($config['model'])
 *     ->execute($prompt);
 * ```
 */
final class AgentCtrl
{
    /**
     * Create a new agent builder for the specified agent type.
     *
     * @return ClaudeCodeBridgeBuilder|CodexBridgeBuilder|OpenCodeBridgeBuilder
     */
    public static function make(AgentType $type): AgentBridgeBuilder
    {
        return match ($type) {
            AgentType::ClaudeCode => new ClaudeCodeBridgeBuilder(),
            AgentType::Codex => new CodexBridgeBuilder(),
            AgentType::OpenCode => new OpenCodeBridgeBuilder(),
        };
    }

    /**
     * Create a Claude Code agent builder.
     */
    public static function claudeCode(): ClaudeCodeBridgeBuilder
    {
        return new ClaudeCodeBridgeBuilder();
    }

    /**
     * Create an OpenAI Codex agent builder.
     */
    public static function codex(): CodexBridgeBuilder
    {
        return new CodexBridgeBuilder();
    }

    /**
     * Create an OpenCode agent builder.
     */
    public static function openCode(): OpenCodeBridgeBuilder
    {
        return new OpenCodeBridgeBuilder();
    }
}
