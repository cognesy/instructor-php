<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Application\Dto;

use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\PermissionMode;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\InputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\PathList;

final readonly class ClaudeRequest
{
    public function __construct(
        private string $prompt,
        private OutputFormat $outputFormat = OutputFormat::Text,
        private PermissionMode $permissionMode = PermissionMode::DefaultMode,
        private ?int $maxTurns = null,
        private ?string $model = null,
        private ?string $systemPrompt = null,
        private ?string $systemPromptFile = null,
        private ?string $appendSystemPrompt = null,
        private ?string $agentsJson = null,
        private ?PathList $additionalDirs = null,
        private bool $includePartialMessages = false,
        private ?InputFormat $inputFormat = null,
        private bool $verbose = false,
        private ?string $permissionPromptTool = null,
        private bool $dangerouslySkipPermissions = false,
        private ?string $resumeSessionId = null,
        private bool $continueMostRecent = false,
        private ?string $stdin = null,
    ) {}

    public function prompt() : string {
        return $this->prompt;
    }

    public function outputFormat() : OutputFormat {
        return $this->outputFormat;
    }

    public function permissionMode() : PermissionMode {
        return $this->permissionMode;
    }

    public function maxTurns() : ?int {
        return $this->maxTurns;
    }

    public function model() : ?string {
        return $this->model;
    }

    public function systemPrompt() : ?string {
        return $this->systemPrompt;
    }

    public function systemPromptFile() : ?string {
        return $this->systemPromptFile;
    }

    public function appendSystemPrompt() : ?string {
        return $this->appendSystemPrompt;
    }

    public function agentsJson() : ?string {
        return $this->agentsJson;
    }

    public function additionalDirs() : PathList {
        if ($this->additionalDirs !== null) {
            return $this->additionalDirs;
        }
        return PathList::none();
    }

    public function includePartialMessages() : bool {
        return $this->includePartialMessages;
    }

    public function inputFormat() : ?InputFormat {
        return $this->inputFormat;
    }

    public function verbose() : bool {
        return $this->verbose;
    }

    public function permissionPromptTool() : ?string {
        return $this->permissionPromptTool;
    }

    public function dangerouslySkipPermissions() : bool {
        return $this->dangerouslySkipPermissions;
    }

    public function resumeSessionId() : ?string {
        return $this->resumeSessionId;
    }

    public function continueMostRecent() : bool {
        return $this->continueMostRecent;
    }

    public function stdin() : ?string {
        return $this->stdin;
    }
}
