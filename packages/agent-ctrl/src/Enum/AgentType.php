<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Enum;

/**
 * Available CLI-based code agent types.
 */
enum AgentType: string
{
    case ClaudeCode = 'claude-code';
    case Codex = 'codex';
    case OpenCode = 'opencode';
    case Pi = 'pi';
    /** @deprecated Gemini CLI bridge is deprecated because the upstream Google CLI flow is obsolete for this package. */
    case Gemini = 'gemini';
}
