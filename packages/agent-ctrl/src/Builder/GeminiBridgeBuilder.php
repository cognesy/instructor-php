<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Builder;

use Cognesy\AgentCtrl\Bridge\GeminiBridge;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;

/**
 * Fluent builder for Gemini CLI bridge.
 */
final class GeminiBridgeBuilder extends AbstractBridgeBuilder
{
    private ?ApprovalMode $approvalMode = null;
    private bool $sandbox = false;
    /** @var list<string>|null */
    private ?array $includeDirectories = null;
    /** @var list<string>|null */
    private ?array $extensions = null;
    /** @var list<string>|null */
    private ?array $allowedTools = null;
    /** @var list<string>|null */
    private ?array $allowedMcpServerNames = null;
    /** @var list<string>|null */
    private ?array $policy = null;
    private ?string $resumeSession = null;
    private bool $debug = false;

    #[\Override]
    public function agentType(): AgentType
    {
        return AgentType::Gemini;
    }

    /**
     * Set the approval mode for tool execution.
     */
    public function withApprovalMode(ApprovalMode $mode): static
    {
        $this->approvalMode = $mode;
        return $this;
    }

    /**
     * Enable YOLO mode (auto-approve all actions).
     */
    public function yolo(): static
    {
        $this->approvalMode = ApprovalMode::Yolo;
        return $this;
    }

    /**
     * Enable plan mode (read-only analysis).
     */
    public function planMode(): static
    {
        $this->approvalMode = ApprovalMode::Plan;
        return $this;
    }

    /**
     * Enable sandbox mode.
     */
    public function withSandbox(bool $enabled = true): static
    {
        $this->sandbox = $enabled;
        return $this;
    }

    /**
     * Add additional workspace directories.
     *
     * @param list<string> $paths
     */
    public function withIncludeDirectories(array $paths): static
    {
        $this->includeDirectories = $paths;
        return $this;
    }

    /**
     * Use specific extensions.
     *
     * @param list<string> $extensions
     */
    public function withExtensions(array $extensions): static
    {
        $this->extensions = $extensions;
        return $this;
    }

    /**
     * Set allowed tools.
     *
     * @param list<string> $tools
     */
    public function withAllowedTools(array $tools): static
    {
        $this->allowedTools = $tools;
        return $this;
    }

    /**
     * Set allowed MCP server names.
     *
     * @param list<string> $names
     */
    public function withAllowedMcpServers(array $names): static
    {
        $this->allowedMcpServerNames = $names;
        return $this;
    }

    /**
     * Add policy files or directories.
     *
     * @param list<string> $paths
     */
    public function withPolicy(array $paths): static
    {
        $this->policy = $paths;
        return $this;
    }

    /**
     * Continue the most recent session.
     */
    public function continueSession(): static
    {
        $this->resumeSession = 'latest';
        return $this;
    }

    /**
     * Resume a specific session by ID or index.
     */
    public function resumeSession(string $sessionId): static
    {
        $this->resumeSession = $sessionId;
        return $this;
    }

    /**
     * Enable debug mode.
     */
    public function debug(bool $enabled = true): static
    {
        $this->debug = $enabled;
        return $this;
    }

    #[\Override]
    public function build(): AgentBridge
    {
        return new GeminiBridge(
            model: $this->model,
            approvalMode: $this->approvalMode,
            sandbox: $this->sandbox,
            includeDirectories: $this->includeDirectories,
            extensions: $this->extensions,
            allowedTools: $this->allowedTools,
            allowedMcpServerNames: $this->allowedMcpServerNames,
            policy: $this->policy,
            resumeSession: $this->resumeSession,
            debug: $this->debug,
            workingDirectory: $this->workingDirectory,
            sandboxDriver: $this->sandboxDriver,
            timeout: $this->timeout,
            events: $this->events,
        );
    }
}
