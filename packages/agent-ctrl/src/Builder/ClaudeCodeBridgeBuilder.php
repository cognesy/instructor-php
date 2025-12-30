<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Builder;

use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Value\PathList;
use Cognesy\AgentCtrl\Bridge\ClaudeCodeBridge;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Enum\AgentType;

/**
 * Fluent builder for Claude Code CLI bridge.
 */
final class ClaudeCodeBridgeBuilder extends AbstractBridgeBuilder
{
    private ?string $systemPrompt = null;
    private ?string $appendSystemPrompt = null;
    private ?int $maxTurns = null;
    private PermissionMode $permissionMode = PermissionMode::BypassPermissions;
    private bool $includePartialMessages = true;
    private bool $verbose = true;  // Required for stream-json with --print
    private ?string $resumeSessionId = null;
    private bool $continueMostRecent = false;
    private ?PathList $additionalDirs = null;

    #[\Override]
    public function agentType(): AgentType
    {
        return AgentType::ClaudeCode;
    }

    /**
     * Set the system prompt for the agent.
     */
    public function withSystemPrompt(string $prompt): static
    {
        $this->systemPrompt = $prompt;
        return $this;
    }

    /**
     * Append to the default system prompt.
     */
    public function appendSystemPrompt(string $prompt): static
    {
        $this->appendSystemPrompt = $prompt;
        return $this;
    }

    /**
     * Set maximum number of agentic turns.
     */
    public function withMaxTurns(int $turns): static
    {
        $this->maxTurns = max(1, $turns);
        return $this;
    }

    /**
     * Set permission mode for tool approvals.
     */
    public function withPermissionMode(PermissionMode $mode): static
    {
        $this->permissionMode = $mode;
        return $this;
    }

    /**
     * Enable verbose output for debugging.
     */
    public function verbose(bool $enabled = true): static
    {
        $this->verbose = $enabled;
        return $this;
    }

    /**
     * Continue the most recent session.
     */
    public function continueSession(): static
    {
        $this->continueMostRecent = true;
        return $this;
    }

    /**
     * Resume a specific session by ID.
     */
    public function resumeSession(string $sessionId): static
    {
        $this->resumeSessionId = $sessionId;
        return $this;
    }

    /**
     * Add additional directories for the agent to access.
     *
     * @param list<string> $paths
     */
    public function withAdditionalDirs(array $paths): static
    {
        $this->additionalDirs = PathList::of($paths);
        return $this;
    }

    #[\Override]
    public function build(): AgentBridge
    {
        return new ClaudeCodeBridge(
            model: $this->model,
            systemPrompt: $this->systemPrompt,
            appendSystemPrompt: $this->appendSystemPrompt,
            maxTurns: $this->maxTurns,
            permissionMode: $this->permissionMode,
            includePartialMessages: $this->includePartialMessages,
            verbose: $this->verbose,
            resumeSessionId: $this->resumeSessionId,
            continueMostRecent: $this->continueMostRecent,
            additionalDirs: $this->additionalDirs,
            sandboxDriver: $this->sandboxDriver,
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
            events: $this->events,
        );
    }
}
