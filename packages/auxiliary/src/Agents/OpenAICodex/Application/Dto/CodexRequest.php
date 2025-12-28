<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Application\Dto;

use Cognesy\Auxiliary\Agents\Common\Value\PathList;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum\SandboxMode;

/**
 * Request configuration for Codex CLI execution
 *
 * Note: Some flags like --ask-for-approval and --search are only available
 * in interactive mode (codex) and not in exec mode (codex exec).
 */
final readonly class CodexRequest
{
    /**
     * @param string $prompt The prompt/query to send to Codex
     * @param OutputFormat $outputFormat Output format (Text or Json for JSONL streaming)
     * @param SandboxMode|null $sandboxMode Sandbox policy (null uses default)
     * @param string|null $model Model override (e.g., 'gpt-5-codex')
     * @param list<string>|null $images Image file paths to attach
     * @param string|null $workingDirectory Working directory for the agent
     * @param PathList|null $additionalDirs Additional writable directories
     * @param string|null $outputSchemaFile JSON Schema file for structured output
     * @param string|null $outputLastMessageFile File to write final message
     * @param string|null $profile Configuration profile name
     * @param bool $fullAuto Shortcut: workspace-write + on-failure approvals
     * @param bool $dangerouslyBypass Skip all approvals and sandbox (DANGEROUS)
     * @param bool $skipGitRepoCheck Allow running outside git repository
     * @param string|null $resumeSessionId Resume specific session by ID
     * @param bool $resumeLast Resume most recent session
     * @param array<string, string>|null $configOverrides Inline config overrides
     * @param string $colorMode Color output mode (always/never/auto)
     */
    public function __construct(
        private string $prompt,
        private OutputFormat $outputFormat = OutputFormat::Text,
        private ?SandboxMode $sandboxMode = null,
        private ?string $model = null,
        private ?array $images = null,
        private ?string $workingDirectory = null,
        private ?PathList $additionalDirs = null,
        private ?string $outputSchemaFile = null,
        private ?string $outputLastMessageFile = null,
        private ?string $profile = null,
        private bool $fullAuto = false,
        private bool $dangerouslyBypass = false,
        private bool $skipGitRepoCheck = false,
        private ?string $resumeSessionId = null,
        private bool $resumeLast = false,
        private ?array $configOverrides = null,
        private string $colorMode = 'never',
    ) {}

    public function prompt(): string {
        return $this->prompt;
    }

    public function outputFormat(): OutputFormat {
        return $this->outputFormat;
    }

    public function sandboxMode(): ?SandboxMode {
        return $this->sandboxMode;
    }

    public function model(): ?string {
        return $this->model;
    }

    /**
     * @return list<string>|null
     */
    public function images(): ?array {
        return $this->images;
    }

    public function workingDirectory(): ?string {
        return $this->workingDirectory;
    }

    public function additionalDirs(): PathList {
        return $this->additionalDirs ?? PathList::none();
    }

    public function outputSchemaFile(): ?string {
        return $this->outputSchemaFile;
    }

    public function outputLastMessageFile(): ?string {
        return $this->outputLastMessageFile;
    }

    public function profile(): ?string {
        return $this->profile;
    }

    public function fullAuto(): bool {
        return $this->fullAuto;
    }

    public function dangerouslyBypass(): bool {
        return $this->dangerouslyBypass;
    }

    public function skipGitRepoCheck(): bool {
        return $this->skipGitRepoCheck;
    }

    public function resumeSessionId(): ?string {
        return $this->resumeSessionId;
    }

    public function resumeLast(): bool {
        return $this->resumeLast;
    }

    /**
     * @return array<string, string>|null
     */
    public function configOverrides(): ?array {
        return $this->configOverrides;
    }

    public function colorMode(): string {
        return $this->colorMode;
    }

    public function isResume(): bool {
        return $this->resumeLast || ($this->resumeSessionId !== null && $this->resumeSessionId !== '');
    }
}
