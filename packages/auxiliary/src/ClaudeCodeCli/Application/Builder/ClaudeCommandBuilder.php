<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\ClaudeCodeCli\Application\Builder;

use Cognesy\Auxiliary\ClaudeCodeCli\Application\Dto\ClaudeRequest;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\InputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Enum\PermissionMode;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\Argv;
use Cognesy\Auxiliary\ClaudeCodeCli\Domain\Value\CommandSpec;

final class ClaudeCommandBuilder
{
    public function buildHeadless(ClaudeRequest $request) : CommandSpec {
        $this->validate($request);

        // Use stdbuf to disable output buffering for real-time streaming
        $argv = Argv::of(['stdbuf', '-o0', 'claude']);
        $argv = $this->appendSessionFlags($argv, $request->continueMostRecent(), $request->resumeSessionId());
        $argv = $argv->with('-p')->with($request->prompt());
        $argv = $this->appendPermissionMode($argv, $request->permissionMode());
        $argv = $this->appendOutputFormat($argv, $request->outputFormat());
        $argv = $this->appendIncludePartial($argv, $request->includePartialMessages());
        $argv = $this->appendInputFormat($argv, $request->inputFormat());
        $argv = $this->appendMaxTurns($argv, $request->maxTurns());
        $argv = $this->appendModel($argv, $request->model());
        $argv = $this->appendSystemPrompts($argv, $request->systemPrompt(), $request->systemPromptFile(), $request->appendSystemPrompt());
        $argv = $this->appendAgents($argv, $request->agentsJson());
        $argv = $this->appendAdditionalDirs($argv, $request->additionalDirs()->toArray());
        $argv = $this->appendVerbose($argv, $request->verbose());
        $argv = $this->appendPermissionPromptTool($argv, $request->permissionPromptTool());
        $argv = $this->appendDangerousSkip($argv, $request->dangerouslySkipPermissions());
        return new CommandSpec($argv, $request->stdin());
    }

    private function appendPermissionMode(Argv $argv, PermissionMode $mode) : Argv {
        if ($mode === PermissionMode::DefaultMode) {
            return $argv;
        }
        return $argv
            ->with('--permission-mode')
            ->with($mode->value);
    }

    private function appendOutputFormat(Argv $argv, OutputFormat $format) : Argv {
        return $argv
            ->with('--output-format')
            ->with($format->value);
    }

    private function appendIncludePartial(Argv $argv, bool $include) : Argv {
        if (!$include) {
            return $argv;
        }
        return $argv->with('--include-partial-messages');
    }

    private function appendInputFormat(Argv $argv, ?InputFormat $format) : Argv {
        if ($format === null) {
            return $argv;
        }
        return $argv
            ->with('--input-format')
            ->with($format->value);
    }

    private function appendMaxTurns(Argv $argv, ?int $maxTurns) : Argv {
        if ($maxTurns === null) {
            return $argv;
        }
        return $argv
            ->with('--max-turns')
            ->with((string)$maxTurns);
    }

    private function appendModel(Argv $argv, ?string $model) : Argv {
        if ($model === null || $model === '') {
            return $argv;
        }
        return $argv
            ->with('--model')
            ->with($model);
    }

    private function appendSystemPrompts(
        Argv $argv,
        ?string $systemPrompt,
        ?string $systemPromptFile,
        ?string $appendSystemPrompt
    ) : Argv {
        if ($systemPrompt !== null && $systemPrompt !== '') {
            return $argv
                ->with('--system-prompt')
                ->with($systemPrompt);
        }
        if ($systemPromptFile !== null && $systemPromptFile !== '') {
            $argv = $argv
                ->with('--system-prompt-file')
                ->with($systemPromptFile);
        }
        if ($appendSystemPrompt !== null && $appendSystemPrompt !== '') {
            $argv = $argv
                ->with('--append-system-prompt')
                ->with($appendSystemPrompt);
        }
        return $argv;
    }

    private function appendAgents(Argv $argv, ?string $agentsJson) : Argv {
        if ($agentsJson === null || $agentsJson === '') {
            return $argv;
        }
        return $argv
            ->with('--agents')
            ->with($agentsJson);
    }

    /**
     * @param list<string> $dirs
     */
    private function appendAdditionalDirs(Argv $argv, array $dirs) : Argv {
        if (count($dirs) === 0) {
            return $argv;
        }
        $current = $argv;
        foreach ($dirs as $dir) {
            $current = $current
                ->with('--add-dir')
                ->with($dir);
        }
        return $current;
    }

    private function appendVerbose(Argv $argv, bool $verbose) : Argv {
        if (!$verbose) {
            return $argv;
        }
        return $argv->with('--verbose');
    }

    private function appendPermissionPromptTool(Argv $argv, ?string $tool) : Argv {
        if ($tool === null || trim($tool) === '') {
            return $argv;
        }
        return $argv
            ->with('--permission-prompt-tool')
            ->with($tool);
    }

    private function appendDangerousSkip(Argv $argv, bool $skip) : Argv {
        if (!$skip) {
            return $argv;
        }
        return $argv->with('--dangerously-skip-permissions');
    }

    private function appendSessionFlags(Argv $argv, bool $continueMostRecent, ?string $resumeSessionId) : Argv {
        if ($continueMostRecent) {
            return $argv->with('--continue');
        }
        if ($resumeSessionId !== null && trim($resumeSessionId) !== '') {
            return $argv
                ->with('--resume')
                ->with($resumeSessionId);
        }
        return $argv;
    }

    private function validate(ClaudeRequest $request) : void {
        if (trim($request->prompt()) === '') {
            throw new \InvalidArgumentException('Prompt must not be empty');
        }
        if ($request->systemPrompt() !== null && $request->systemPromptFile() !== null) {
            throw new \InvalidArgumentException('systemPrompt and systemPromptFile cannot be used together');
        }
        if ($request->continueMostRecent() && $request->resumeSessionId() !== null) {
            throw new \InvalidArgumentException('Cannot set both continueMostRecent and resumeSessionId');
        }
        if ($request->includePartialMessages() && $request->outputFormat() !== OutputFormat::StreamJson) {
            throw new \InvalidArgumentException('--include-partial-messages requires output format stream-json');
        }
        if ($request->inputFormat() === InputFormat::StreamJson && $request->outputFormat() !== OutputFormat::StreamJson) {
            throw new \InvalidArgumentException('stream-json input requires output format stream-json');
        }
    }
}
