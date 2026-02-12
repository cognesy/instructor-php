<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Builder;

use Cognesy\AgentCtrl\Common\Value\PathList;
use Cognesy\AgentCtrl\OpenAICodex\Domain\Enum\SandboxMode;
use Cognesy\AgentCtrl\Bridge\CodexBridge;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Enum\AgentType;

/**
 * Fluent builder for OpenAI Codex CLI bridge.
 */
final class CodexBridgeBuilder extends AbstractBridgeBuilder
{
    private ?SandboxMode $sandboxMode = null;
    private bool $fullAuto = true;
    private bool $dangerouslyBypass = false;
    private bool $skipGitRepoCheck = false;
    private ?string $resumeSessionId = null;
    private bool $resumeLast = false;
    private ?PathList $additionalDirs = null;
    /** @var list<string>|null */
    private ?array $images = null;

    #[\Override]
    public function agentType(): AgentType
    {
        return AgentType::Codex;
    }

    /**
     * Set sandbox mode for file/network access control.
     */
    public function withSandbox(SandboxMode $mode): static
    {
        $this->sandboxMode = $mode;
        return $this;
    }

    /**
     * Disable sandbox for unrestricted access.
     *
     * Note: This sets sandbox to DangerFullAccess mode which provides
     * full filesystem and network access. Use with caution.
     */
    public function disableSandbox(): static
    {
        $this->sandboxMode = SandboxMode::DangerFullAccess;
        return $this;
    }

    /**
     * Enable full auto mode (workspace write + on-failure approvals).
     */
    public function fullAuto(bool $enabled = true): static
    {
        $this->fullAuto = $enabled;
        return $this;
    }

    /**
     * Skip all approvals and sandbox (DANGEROUS).
     */
    public function dangerouslyBypass(bool $enabled = true): static
    {
        $this->dangerouslyBypass = $enabled;
        return $this;
    }

    /**
     * Allow running outside a git repository.
     */
    public function skipGitRepoCheck(bool $enabled = true): static
    {
        $this->skipGitRepoCheck = $enabled;
        return $this;
    }

    /**
     * Resume the most recent session.
     */
    public function continueSession(): static
    {
        $this->resumeLast = true;
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
     * Add additional writable directories.
     *
     * @param list<string> $paths
     */
    public function withAdditionalDirs(array $paths): static
    {
        $this->additionalDirs = PathList::of($paths);
        return $this;
    }

    /**
     * Attach image files to the prompt.
     *
     * @param list<string> $imagePaths
     */
    public function withImages(array $imagePaths): static
    {
        $this->images = $imagePaths;
        return $this;
    }

    #[\Override]
    public function build(): AgentBridge
    {
        return new CodexBridge(
            model: $this->model,
            sandboxMode: $this->sandboxMode,
            fullAuto: $this->fullAuto,
            dangerouslyBypass: $this->dangerouslyBypass,
            skipGitRepoCheck: $this->skipGitRepoCheck,
            resumeSessionId: $this->resumeSessionId,
            resumeLast: $this->resumeLast,
            additionalDirs: $this->additionalDirs,
            images: $this->images,
            workingDirectory: $this->workingDirectory,
            sandboxDriver: $this->sandboxDriver,
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
            events: $this->events,
        );
    }
}
