<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Domain\Enum;

enum PermissionMode: string
{
    case DefaultMode = 'default';
    case Plan = 'plan';
    case AcceptEdits = 'acceptEdits';
    case BypassPermissions = 'bypassPermissions';
}
