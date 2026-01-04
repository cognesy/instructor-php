<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Enums;

/**
 * @deprecated Use SubagentRegistry and SubagentSpec instead. Will be removed in next major version.
 */
enum AgentType: string
{
    case Explore = 'explore';
    case Code = 'code';
    case Plan = 'plan';

    public function description(): string {
        return match($this) {
            self::Explore => 'Fast agent for exploring codebases. Read-only access to files and limited bash commands.',
            self::Code => 'Full-featured coding agent with access to all file and bash tools.',
            self::Plan => 'Planning agent for designing solutions. Read-only access, no code execution.',
        };
    }

    public function systemPromptAddition(): string {
        return match($this) {
            self::Explore => "You are an exploration agent. Focus on reading and understanding code. Do not modify files.",
            self::Code => "You are a coding agent. You can read, write, and execute code to accomplish tasks.",
            self::Plan => "You are a planning agent. Design solutions and create implementation plans. Do not execute code.",
        };
    }
}
