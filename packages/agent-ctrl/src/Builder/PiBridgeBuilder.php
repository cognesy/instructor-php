<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Builder;

use Cognesy\AgentCtrl\Bridge\PiBridge;
use Cognesy\AgentCtrl\Contract\AgentBridge;
use Cognesy\AgentCtrl\Enum\AgentType;
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;

/**
 * Fluent builder for Pi CLI bridge.
 */
final class PiBridgeBuilder extends AbstractBridgeBuilder
{
    private ?string $provider = null;
    private ?ThinkingLevel $thinkingLevel = null;
    private ?string $systemPrompt = null;
    private ?string $appendSystemPrompt = null;
    /** @var list<string>|null */
    private ?array $tools = null;
    private bool $noTools = false;
    /** @var list<string>|null */
    private ?array $files = null;
    /** @var list<string>|null */
    private ?array $extensions = null;
    private bool $noExtensions = false;
    /** @var list<string>|null */
    private ?array $skills = null;
    private bool $noSkills = false;
    private ?string $apiKey = null;
    private bool $continueSession = false;
    private ?string $sessionId = null;
    private bool $noSession = false;
    private ?string $sessionDir = null;
    private bool $verbose = false;

    #[\Override]
    public function agentType(): AgentType
    {
        return AgentType::Pi;
    }

    /**
     * Set the provider explicitly (anthropic, openai, google, etc.)
     */
    public function withProvider(string $provider): static
    {
        $this->provider = $provider;
        return $this;
    }

    /**
     * Set the thinking level.
     */
    public function withThinking(ThinkingLevel $level): static
    {
        $this->thinkingLevel = $level;
        return $this;
    }

    /**
     * Replace the default system prompt.
     */
    public function withSystemPrompt(string|\Stringable $prompt): static
    {
        $this->systemPrompt = (string) $prompt;
        return $this;
    }

    /**
     * Append to the default system prompt.
     */
    public function appendSystemPrompt(string|\Stringable $prompt): static
    {
        $this->appendSystemPrompt = (string) $prompt;
        return $this;
    }

    /**
     * Enable specific built-in tools.
     *
     * @param list<string> $tools e.g. ['read', 'bash', 'edit']
     */
    public function withTools(array $tools): static
    {
        $this->tools = $tools;
        return $this;
    }

    /**
     * Disable all built-in tools.
     */
    public function noTools(): static
    {
        $this->noTools = true;
        return $this;
    }

    /**
     * Attach files to the prompt via @-prefix.
     *
     * @param list<string> $filePaths
     */
    public function withFiles(array $filePaths): static
    {
        $this->files = $filePaths;
        return $this;
    }

    /**
     * Load extensions from paths or sources.
     *
     * @param list<string> $extensions
     */
    public function withExtensions(array $extensions): static
    {
        $this->extensions = $extensions;
        return $this;
    }

    /**
     * Disable extension discovery.
     */
    public function noExtensions(): static
    {
        $this->noExtensions = true;
        return $this;
    }

    /**
     * Load skills from paths.
     *
     * @param list<string> $skills
     */
    public function withSkills(array $skills): static
    {
        $this->skills = $skills;
        return $this;
    }

    /**
     * Disable skill discovery.
     */
    public function noSkills(): static
    {
        $this->noSkills = true;
        return $this;
    }

    /**
     * Override API key.
     */
    public function withApiKey(string $apiKey): static
    {
        $this->apiKey = $apiKey;
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
     * Ephemeral mode — don't save session.
     */
    public function ephemeral(): static
    {
        $this->noSession = true;
        return $this;
    }

    /**
     * Set custom session storage directory.
     */
    public function withSessionDir(string $dir): static
    {
        $this->sessionDir = $dir;
        return $this;
    }

    /**
     * Enable verbose output.
     */
    public function verbose(bool $enabled = true): static
    {
        $this->verbose = $enabled;
        return $this;
    }

    #[\Override]
    public function build(): AgentBridge
    {
        return new PiBridge(
            executionId: $this->executionId(),
            model: $this->model,
            provider: $this->provider,
            thinkingLevel: $this->thinkingLevel,
            systemPrompt: $this->systemPrompt,
            appendSystemPrompt: $this->appendSystemPrompt,
            tools: $this->tools,
            noTools: $this->noTools,
            files: $this->files,
            extensions: $this->extensions,
            noExtensions: $this->noExtensions,
            skills: $this->skills,
            noSkills: $this->noSkills,
            apiKey: $this->apiKey,
            continueSession: $this->continueSession,
            sessionId: $this->sessionId,
            noSession: $this->noSession,
            sessionDir: $this->sessionDir,
            verbose: $this->verbose,
            workingDirectory: $this->workingDirectory,
            sandboxDriver: $this->sandboxDriver,
            timeout: $this->timeout,
            events: $this->events,
        );
    }
}
