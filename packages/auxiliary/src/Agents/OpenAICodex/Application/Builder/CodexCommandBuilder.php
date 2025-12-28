<?php declare(strict_types=1);

namespace Cognesy\Auxiliary\Agents\OpenAICodex\Application\Builder;

use Cognesy\Auxiliary\Agents\Common\Value\Argv;
use Cognesy\Auxiliary\Agents\Common\Value\CommandSpec;
use Cognesy\Auxiliary\Agents\OpenAICodex\Application\Dto\CodexRequest;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum\OutputFormat;
use Cognesy\Auxiliary\Agents\OpenAICodex\Domain\Enum\SandboxMode;

/**
 * Builds command line arguments for Codex CLI exec command
 */
final class CodexCommandBuilder
{
    /**
     * Build command spec for headless execution via `codex exec`
     */
    public function buildExec(CodexRequest $request): CommandSpec
    {
        $this->validate($request);

        // Use stdbuf for unbuffered output (Linux only)
        $argv = Argv::of(['stdbuf', '-o0', 'codex', 'exec']);

        // Handle resume subcommand - must come before prompt
        $argv = $this->appendResumeSubcommand($argv, $request);

        // Prompt goes after exec/resume subcommand
        $argv = $argv->with($request->prompt());

        // Add flags (order doesn't matter for these)
        $argv = $this->appendSandboxMode($argv, $request->sandboxMode());
        $argv = $this->appendOutputFormat($argv, $request->outputFormat());
        $argv = $this->appendColorMode($argv, $request->colorMode());
        $argv = $this->appendModel($argv, $request->model());
        $argv = $this->appendImages($argv, $request->images());
        $argv = $this->appendWorkingDirectory($argv, $request->workingDirectory());
        $argv = $this->appendAdditionalDirs($argv, $request->additionalDirs()->toArray());
        $argv = $this->appendOutputSchema($argv, $request->outputSchemaFile());
        $argv = $this->appendOutputLastMessage($argv, $request->outputLastMessageFile());
        $argv = $this->appendProfile($argv, $request->profile());
        $argv = $this->appendFullAuto($argv, $request->fullAuto());
        $argv = $this->appendDangerouslyBypass($argv, $request->dangerouslyBypass());
        $argv = $this->appendSkipGitRepoCheck($argv, $request->skipGitRepoCheck());
        $argv = $this->appendConfigOverrides($argv, $request->configOverrides());

        return new CommandSpec($argv, null);
    }

    private function appendResumeSubcommand(Argv $argv, CodexRequest $request): Argv
    {
        if (!$request->isResume()) {
            return $argv;
        }

        // Resume is a subcommand: codex exec resume [--last | SESSION_ID]
        $argv = $argv->with('resume');

        if ($request->resumeLast()) {
            return $argv->with('--last');
        }

        $sessionId = $request->resumeSessionId();
        if ($sessionId !== null && $sessionId !== '') {
            return $argv->with($sessionId);
        }

        return $argv;
    }

    private function appendSandboxMode(Argv $argv, ?SandboxMode $mode): Argv
    {
        if ($mode === null) {
            return $argv;
        }

        return $argv
            ->with('--sandbox')
            ->with($mode->value);
    }

    private function appendOutputFormat(Argv $argv, OutputFormat $format): Argv
    {
        if ($format === OutputFormat::Json) {
            return $argv->with('--json');
        }

        return $argv;
    }

    private function appendColorMode(Argv $argv, string $mode): Argv
    {
        if ($mode === 'auto') {
            return $argv;
        }

        return $argv
            ->with('--color')
            ->with($mode);
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

    /**
     * @param list<string>|null $images
     */
    private function appendImages(Argv $argv, ?array $images): Argv
    {
        if ($images === null || count($images) === 0) {
            return $argv;
        }

        // Multiple images as comma-separated
        return $argv
            ->with('--image')
            ->with(implode(',', $images));
    }

    private function appendWorkingDirectory(Argv $argv, ?string $dir): Argv
    {
        if ($dir === null || $dir === '') {
            return $argv;
        }

        return $argv
            ->with('--cd')
            ->with($dir);
    }

    /**
     * @param list<string> $dirs
     */
    private function appendAdditionalDirs(Argv $argv, array $dirs): Argv
    {
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

    private function appendOutputSchema(Argv $argv, ?string $schemaFile): Argv
    {
        if ($schemaFile === null || $schemaFile === '') {
            return $argv;
        }

        return $argv
            ->with('--output-schema')
            ->with($schemaFile);
    }

    private function appendOutputLastMessage(Argv $argv, ?string $file): Argv
    {
        if ($file === null || $file === '') {
            return $argv;
        }

        return $argv
            ->with('--output-last-message')
            ->with($file);
    }

    private function appendProfile(Argv $argv, ?string $profile): Argv
    {
        if ($profile === null || $profile === '') {
            return $argv;
        }

        return $argv
            ->with('--profile')
            ->with($profile);
    }

    private function appendFullAuto(Argv $argv, bool $fullAuto): Argv
    {
        if (!$fullAuto) {
            return $argv;
        }

        return $argv->with('--full-auto');
    }

    private function appendDangerouslyBypass(Argv $argv, bool $bypass): Argv
    {
        if (!$bypass) {
            return $argv;
        }

        return $argv->with('--dangerously-bypass-approvals-and-sandbox');
    }

    private function appendSkipGitRepoCheck(Argv $argv, bool $skip): Argv
    {
        if (!$skip) {
            return $argv;
        }

        return $argv->with('--skip-git-repo-check');
    }

    /**
     * @param array<string, string>|null $overrides
     */
    private function appendConfigOverrides(Argv $argv, ?array $overrides): Argv
    {
        if ($overrides === null || count($overrides) === 0) {
            return $argv;
        }

        $current = $argv;
        foreach ($overrides as $key => $value) {
            $current = $current
                ->with('--config')
                ->with("{$key}={$value}");
        }

        return $current;
    }

    private function validate(CodexRequest $request): void
    {
        if (trim($request->prompt()) === '') {
            throw new \InvalidArgumentException('Prompt must not be empty');
        }

        if ($request->resumeLast() && $request->resumeSessionId() !== null) {
            throw new \InvalidArgumentException('Cannot set both resumeLast and resumeSessionId');
        }

        if ($request->fullAuto() && $request->dangerouslyBypass()) {
            throw new \InvalidArgumentException('fullAuto and dangerouslyBypass are mutually exclusive');
        }
    }
}
