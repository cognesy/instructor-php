<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\Enum;

/**
 * Available CLI-based code agent types.
 */
enum AgentType: string
{
    case ClaudeCode = 'claude-code';
    case Codex = 'codex';
    case OpenCode = 'opencode';
}
