<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\ClaudeCode\Application\Builder;

use Cognesy\AgentCtrl\ClaudeCode\Application\Dto\ClaudeRequest;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\InputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\OutputFormat;
use Cognesy\AgentCtrl\ClaudeCode\Domain\Enum\PermissionMode;
use Cognesy\AgentCtrl\Common\Value\PathList;

final class ClaudeRequestBuilder
{
    private string $prompt = '';
    private OutputFormat $outputFormat = OutputFormat::Text;
    private PermissionMode $permissionMode = PermissionMode::DefaultMode;
    private ?int $maxTurns = null;
    private ?string $model = null;
    private ?string $systemPrompt = null;
    private ?string $systemPromptFile = null;
    private ?string $appendSystemPrompt = null;
    private ?string $agentsJson = null;
    private ?PathList $additionalDirs = null;
    private bool $includePartialMessages = false;
    private ?InputFormat $inputFormat = null;
    private bool $verbose = false;
    private ?string $permissionPromptTool = null;
    private bool $dangerouslySkipPermissions = false;
    private ?string $resumeSessionId = null;
    private bool $continueMostRecent = false;
    private ?string $stdin = null;

    public static function new() : self {
        return new self();
    }

    public function withPrompt(string $prompt) : self {
        $this->prompt = $prompt;
        return $this;
    }

    public function withOutputFormat(OutputFormat $outputFormat) : self {
        $this->outputFormat = $outputFormat;
        return $this;
    }

    public function withPermissionMode(PermissionMode $permissionMode) : self {
        $this->permissionMode = $permissionMode;
        return $this;
    }

    public function withMaxTurns(?int $maxTurns) : self {
        $this->maxTurns = $maxTurns;
        return $this;
    }

    public function withModel(?string $model) : self {
        $this->model = $model;
        return $this;
    }

    public function withSystemPrompt(?string $systemPrompt) : self {
        $this->systemPrompt = $systemPrompt;
        return $this;
    }

    public function withSystemPromptFile(?string $systemPromptFile) : self {
        $this->systemPromptFile = $systemPromptFile;
        return $this;
    }

    public function withAppendSystemPrompt(?string $appendSystemPrompt) : self {
        $this->appendSystemPrompt = $appendSystemPrompt;
        return $this;
    }

    public function withAgentsJson(?string $agentsJson) : self {
        $this->agentsJson = $agentsJson;
        return $this;
    }

    public function withAdditionalDirs(?PathList $additionalDirs) : self {
        $this->additionalDirs = $additionalDirs;
        return $this;
    }

    public function withIncludePartialMessages(bool $includePartialMessages = true) : self {
        $this->includePartialMessages = $includePartialMessages;
        return $this;
    }

    public function withInputFormat(?InputFormat $inputFormat) : self {
        $this->inputFormat = $inputFormat;
        return $this;
    }

    public function withVerbose(bool $verbose = true) : self {
        $this->verbose = $verbose;
        return $this;
    }

    public function withPermissionPromptTool(?string $permissionPromptTool) : self {
        $this->permissionPromptTool = $permissionPromptTool;
        return $this;
    }

    public function withDangerouslySkipPermissions(bool $dangerouslySkipPermissions = true) : self {
        $this->dangerouslySkipPermissions = $dangerouslySkipPermissions;
        return $this;
    }

    public function withResumeSessionId(?string $resumeSessionId) : self {
        $this->resumeSessionId = $resumeSessionId;
        return $this;
    }

    public function withContinueMostRecent(bool $continueMostRecent = true) : self {
        $this->continueMostRecent = $continueMostRecent;
        return $this;
    }

    public function withStdin(?string $stdin) : self {
        $this->stdin = $stdin;
        return $this;
    }

    public function build() : ClaudeRequest {
        return new ClaudeRequest(
            prompt: $this->prompt,
            outputFormat: $this->outputFormat,
            permissionMode: $this->permissionMode,
            maxTurns: $this->maxTurns,
            model: $this->model,
            systemPrompt: $this->systemPrompt,
            systemPromptFile: $this->systemPromptFile,
            appendSystemPrompt: $this->appendSystemPrompt,
            agentsJson: $this->agentsJson,
            additionalDirs: $this->additionalDirs,
            includePartialMessages: $this->includePartialMessages,
            inputFormat: $this->inputFormat,
            verbose: $this->verbose,
            permissionPromptTool: $this->permissionPromptTool,
            dangerouslySkipPermissions: $this->dangerouslySkipPermissions,
            resumeSessionId: $this->resumeSessionId,
            continueMostRecent: $this->continueMostRecent,
            stdin: $this->stdin,
        );
    }
}
