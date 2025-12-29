<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Builder;

use Cognesy\AgentCtrl\Bridge\OpenCodeBridge;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Enum\AgentType;

/**
 * Fluent builder for OpenCode CLI bridge.
 */
final class OpenCodeBridgeBuilder extends AbstractBridgeBuilder
{
    private ?string $agent = null;
    /** @var list<string>|null */
    private ?array $files = null;
    private bool $continueSession = false;
    private ?string $sessionId = null;
    private bool $share = false;
    private ?string $title = null;

    #[\Override]
    public function agentType(): AgentType
    {
        return AgentType::OpenCode;
    }

    /**
     * Use a named agent.
     */
    public function withAgent(string $agentName): static
    {
        $this->agent = $agentName;
        return $this;
    }

    /**
     * Attach files to the prompt.
     *
     * @param list<string> $filePaths
     */
    public function withFiles(array $filePaths): static
    {
        $this->files = $filePaths;
        return $this;
    }

    /**
     * Continue the last session.
     */
    public function continueSession(): static
    {
        $this->continueSession = true;
        return $this;
    }

    /**
     * Resume a specific session by ID.
     */
    public function resumeSession(string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    /**
     * Share the session after completion.
     */
    public function shareSession(): static
    {
        $this->share = true;
        return $this;
    }

    /**
     * Set a title for the session.
     */
    public function withTitle(string $title): static
    {
        $this->title = $title;
        return $this;
    }

    #[\Override]
    public function build(): AgentBridge
    {
        return new OpenCodeBridge(
            model: $this->model,
            agent: $this->agent,
            files: $this->files,
            continueSession: $this->continueSession,
            sessionId: $this->sessionId,
            share: $this->share,
            title: $this->title,
            sandboxDriver: $this->sandboxDriver,
            timeout: $this->timeout,
            maxRetries: $this->maxRetries,
        );
    }
}
