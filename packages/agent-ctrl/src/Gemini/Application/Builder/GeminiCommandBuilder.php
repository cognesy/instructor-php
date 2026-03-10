<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Gemini\Application\Builder;

use Cognesy\AgentCtrl\Common\Builder\BuildsCliArgv;
use Cognesy\AgentCtrl\Common\Validation\CliArgumentValidator;
use Cognesy\AgentCtrl\Gemini\Application\Dto\GeminiRequest;
use Cognesy\AgentCtrl\Gemini\Domain\Enum\ApprovalMode;
use Cognesy\Sandbox\Value\Argv;
use Cognesy\Sandbox\Value\CommandSpec;
use InvalidArgumentException;

/**
 * Builds command line arguments for Gemini CLI
 */
final class GeminiCommandBuilder
{
    use BuildsCliArgv;

    /**
     * Build command spec for headless execution via `gemini --output-format stream-json --prompt "..."`
     */
    public function build(GeminiRequest $request): CommandSpec
    {
        $this->validate($request);

        $argv = $this->baseArgv(['gemini']);

        $argv = $this->appendOutputFormat($argv, $request);
        $argv = $this->appendModel($argv, $request->model());
        $argv = $this->appendApprovalMode($argv, $request->approvalMode());
        $argv = $this->appendSandbox($argv, $request->sandbox());
        $argv = $this->appendRepeatedFlag($argv, '--include-directories', $request->includeDirectories());
        $argv = $this->appendRepeatedFlag($argv, '--extensions', $request->extensions());
        $argv = $this->appendRepeatedFlag($argv, '--allowed-tools', $request->allowedTools());
        $argv = $this->appendRepeatedFlag($argv, '--allowed-mcp-server-names', $request->allowedMcpServerNames());
        $argv = $this->appendRepeatedFlag($argv, '--policy', $request->policy());
        $argv = $this->appendResume($argv, $request->resumeSession());
        $argv = $this->appendDebug($argv, $request->debug());

        // Prompt via --prompt flag for headless mode
        $argv = $argv
            ->with('--prompt')
            ->with($request->prompt());

        return new CommandSpec($argv, null);
    }

    private function appendOutputFormat(Argv $argv, GeminiRequest $request): Argv
    {
        return $argv
            ->with('--output-format')
            ->with($request->outputFormat()->value);
    }

    private function appendModel(Argv $argv, ?string $model): Argv
    {
        if ($model === null || $model === '') {
            return $argv;
        }

        return $argv
            ->with('--model')
            ->with($model);
    }

    private function appendApprovalMode(Argv $argv, ?ApprovalMode $mode): Argv
    {
        if ($mode === null) {
            return $argv;
        }

        return $argv
            ->with('--approval-mode')
            ->with($mode->value);
    }

    private function appendSandbox(Argv $argv, bool $sandbox): Argv
    {
        if (!$sandbox) {
            return $argv;
        }

        return $argv->with('--sandbox');
    }

    /**
     * @param list<string>|null $values
     */
    private function appendRepeatedFlag(Argv $argv, string $flag, ?array $values): Argv
    {
        if ($values === null || count($values) === 0) {
            return $argv;
        }

        $current = $argv;
        foreach ($values as $value) {
            $current = $current
                ->with($flag)
                ->with($value);
        }

        return $current;
    }

    private function appendResume(Argv $argv, ?string $resume): Argv
    {
        if ($resume === null || $resume === '') {
            return $argv;
        }

        return $argv
            ->with('--resume')
            ->with($resume);
    }

    private function appendDebug(Argv $argv, bool $debug): Argv
    {
        if (!$debug) {
            return $argv;
        }

        return $argv->with('--debug');
    }

    private function validate(GeminiRequest $request): void
    {
        if (trim($request->prompt()) === '') {
            throw new InvalidArgumentException('Prompt must not be empty');
        }

        CliArgumentValidator::validateModel($request->model());
    }
}
