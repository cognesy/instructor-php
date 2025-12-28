<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum;

/**
 * Sandbox policy modes for Codex CLI
 *
 * Controls what filesystem and network access the agent has.
 */
enum SandboxMode: string
{
    /** Read-only access (default). No writes, no network. */
    case ReadOnly = 'read-only';

    /** Write access to workspace only. No network by default. */
    case WorkspaceWrite = 'workspace-write';

    /** Full access including network. Use with caution. */
    case DangerFullAccess = 'danger-full-access';
}
