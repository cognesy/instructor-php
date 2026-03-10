<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Application\Dto;

use Cognesy\AgentCtrl\Pi\Domain\Enum\OutputMode;
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;
use Cognesy\AgentCtrl\Pi\Domain\ValueObject\PiSessionId;

/**
 * Request configuration for Pi CLI execution
 */
final readonly class PiRequest
{
    private ?PiSessionId $sessionId;

    /**
     * @param string $prompt The message/prompt to send
     * @param OutputMode $outputMode Output mode (json or rpc)
     * @param string|null $model Model pattern or ID (supports provider/id and provider/id:thinking)
     * @param string|null $provider Explicit provider override
     * @param ThinkingLevel|null $thinkingLevel Thinking level
     * @param string|null $systemPrompt Replace default system prompt
     * @param string|null $appendSystemPrompt Append to system prompt
     * @param list<string>|null $tools List of tool names to enable
     * @param bool $noTools Disable all built-in tools
     * @param list<string>|null $files File paths to attach as @-prefixed args
     * @param list<string>|null $extensions Extension paths/sources to load
     * @param bool $noExtensions Disable extension discovery
     * @param list<string>|null $skills Skill paths to load
     * @param bool $noSkills Disable skill discovery
     * @param string|null $apiKey API key override
     * @param bool $continueSession Continue the last session
     * @param PiSessionId|string|null $sessionId Specific session to resume
     * @param bool $noSession Ephemeral mode (don't save)
     * @param string|null $sessionDir Custom session storage directory
     * @param bool $verbose Verbose output
     */
    public function __construct(
        private string $prompt,
        private OutputMode $outputMode = OutputMode::Json,
        private ?string $model = null,
        private ?string $provider = null,
        private ?ThinkingLevel $thinkingLevel = null,
        private ?string $systemPrompt = null,
        private ?string $appendSystemPrompt = null,
        private ?array $tools = null,
        private bool $noTools = false,
        private ?array $files = null,
        private ?array $extensions = null,
        private bool $noExtensions = false,
        private ?array $skills = null,
        private bool $noSkills = false,
        private ?string $apiKey = null,
        private bool $continueSession = false,
        PiSessionId|string|null $sessionId = null,
        private bool $noSession = false,
        private ?string $sessionDir = null,
        private bool $verbose = false,
    ) {
        $this->sessionId = match (true) {
            $sessionId instanceof PiSessionId => $sessionId,
            is_string($sessionId) && $sessionId !== '' => PiSessionId::fromString($sessionId),
            default => null,
        };
    }

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function outputMode(): OutputMode
    {
        return $this->outputMode;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function provider(): ?string
    {
        return $this->provider;
    }

    public function thinkingLevel(): ?ThinkingLevel
    {
        return $this->thinkingLevel;
    }

    public function systemPrompt(): ?string
    {
        return $this->systemPrompt;
    }

    public function appendSystemPrompt(): ?string
    {
        return $this->appendSystemPrompt;
    }

    /**
     * @return list<string>|null
     */
    public function tools(): ?array
    {
        return $this->tools;
    }

    public function noTools(): bool
    {
        return $this->noTools;
    }

    /**
     * @return list<string>|null
     */
    public function files(): ?array
    {
        return $this->files;
    }

    /**
     * @return list<string>|null
     */
    public function extensions(): ?array
    {
        return $this->extensions;
    }

    public function noExtensions(): bool
    {
        return $this->noExtensions;
    }

    /**
     * @return list<string>|null
     */
    public function skills(): ?array
    {
        return $this->skills;
    }

    public function noSkills(): bool
    {
        return $this->noSkills;
    }

    public function apiKey(): ?string
    {
        return $this->apiKey;
    }

    public function continueSession(): bool
    {
        return $this->continueSession;
    }

    public function sessionId(): ?PiSessionId
    {
        return $this->sessionId;
    }

    public function noSession(): bool
    {
        return $this->noSession;
    }

    public function sessionDir(): ?string
    {
        return $this->sessionDir;
    }

    public function verbose(): bool
    {
        return $this->verbose;
    }

    public function isResume(): bool
    {
        return $this->continueSession || $this->sessionId !== null;
    }
}
