<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Application\Dto;

use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\OutputFormat;

/**
 * Request configuration for Gemini CLI execution
 */
final readonly class GeminiRequest
{
    /**
     * @param string $prompt The message/prompt to send
     * @param OutputFormat $outputFormat Output format (always stream-json for bridge)
     * @param string|null $model Model alias (pro, flash, flash-lite, auto) or full model name
     * @param ApprovalMode|null $approvalMode Approval mode for tool execution
     * @param bool $sandbox Run in sandboxed environment
     * @param list<string>|null $includeDirectories Additional workspace directories
     * @param list<string>|null $extensions Specific extensions to use
     * @param list<string>|null $allowedTools Tool allowlist
     * @param list<string>|null $allowedMcpServerNames MCP server names allowlist
     * @param list<string>|null $policy Additional policy files/directories
     * @param string|null $resumeSession Resume session: 'latest', index, or UUID
     * @param bool $debug Debug mode
     */
    public function __construct(
        private string $prompt,
        private OutputFormat $outputFormat = OutputFormat::StreamJson,
        private ?string $model = null,
        private ?ApprovalMode $approvalMode = null,
        private bool $sandbox = false,
        private ?array $includeDirectories = null,
        private ?array $extensions = null,
        private ?array $allowedTools = null,
        private ?array $allowedMcpServerNames = null,
        private ?array $policy = null,
        private ?string $resumeSession = null,
        private bool $debug = false,
    ) {}

    public function prompt(): string
    {
        return $this->prompt;
    }

    public function outputFormat(): OutputFormat
    {
        return $this->outputFormat;
    }

    public function model(): ?string
    {
        return $this->model;
    }

    public function approvalMode(): ?ApprovalMode
    {
        return $this->approvalMode;
    }

    public function sandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * @return list<string>|null
     */
    public function includeDirectories(): ?array
    {
        return $this->includeDirectories;
    }

    /**
     * @return list<string>|null
     */
    public function extensions(): ?array
    {
        return $this->extensions;
    }

    /**
     * @return list<string>|null
     */
    public function allowedTools(): ?array
    {
        return $this->allowedTools;
    }

    /**
     * @return list<string>|null
     */
    public function allowedMcpServerNames(): ?array
    {
        return $this->allowedMcpServerNames;
    }

    /**
     * @return list<string>|null
     */
    public function policy(): ?array
    {
        return $this->policy;
    }

    public function resumeSession(): ?string
    {
        return $this->resumeSession;
    }

    public function debug(): bool
    {
        return $this->debug;
    }

    public function isResume(): bool
    {
        return $this->resumeSession !== null && $this->resumeSession !== '';
    }
}
