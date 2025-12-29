<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Application\Dto;

use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\OpenCode\Domain\Value\ModelId;

/**
 * Request configuration for OpenCode CLI run command
 */
final readonly class OpenCodeRequest
{
    /**
     * @param string $prompt The message/prompt to send
     * @param OutputFormat $outputFormat Output format (default or json)
     * @param string|ModelId|null $model Model in provider/model format
     * @param string|null $agent Named agent to use
     * @param list<string>|null $files File paths to attach
     * @param bool $continueSession Continue the last session
     * @param string|null $sessionId Specific session ID to continue
     * @param bool $share Share the session after completion
     * @param string|null $title Session title
     * @param string|null $attachUrl Attach to running server URL
     * @param int|null $port Local server port
     * @param string|null $command Command to run (message becomes args)
     */
    public function __construct(
        private string $prompt,
        private OutputFormat $outputFormat = OutputFormat::Default,
        private string|ModelId|null $model = null,
        private ?string $agent = null,
        private ?array $files = null,
        private bool $continueSession = false,
        private ?string $sessionId = null,
        private bool $share = false,
        private ?string $title = null,
        private ?string $attachUrl = null,
        private ?int $port = null,
        private ?string $command = null,
    ) {}

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function outputFormat(): OutputFormat
    {
        return $this->outputFormat;
    }

    public function model(): string|ModelId|null
    {
        return $this->model;
    }

    /**
     * Get model as string for CLI
     */
    public function modelString(): ?string
    {
        if ($this->model === null) {
            return null;
        }
        return $this->model instanceof ModelId
            ? $this->model->toString()
            : $this->model;
    }

    public function agent(): ?string
    {
        return $this->agent;
    }

    /**
     * @return list<string>|null
     */
    public function files(): ?array
    {
        return $this->files;
    }

    public function continueSession(): bool
    {
        return $this->continueSession;
    }

    public function sessionId(): ?string
    {
        return $this->sessionId;
    }

    public function share(): bool
    {
        return $this->share;
    }

    public function title(): ?string
    {
        return $this->title;
    }

    public function attachUrl(): ?string
    {
        return $this->attachUrl;
    }

    public function port(): ?int
    {
        return $this->port;
    }

    public function command(): ?string
    {
        return $this->command;
    }

    /**
     * Check if this is a session resume request
     */
    public function isResume(): bool
    {
        return $this->continueSession || ($this->sessionId !== null && $this->sessionId !== '');
    }
}
