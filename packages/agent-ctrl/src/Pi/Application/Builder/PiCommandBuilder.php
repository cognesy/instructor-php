<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Pi\Application\Builder;

use Cognesy\AgentCtrl\Common\Builder\BuildsCliArgv;
use Cognesy\AgentCtrl\Common\Validation\CliArgumentValidator;
use Cognesy\AgentCtrl\Pi\Application\Dto\PiRequest;
use Cognesy\AgentCtrl\Pi\Domain\Enum\OutputMode;
use Cognesy\AgentCtrl\Pi\Domain\Enum\ThinkingLevel;
use Cognesy\Sandbox\Value\Argv;
use Cognesy\Sandbox\Value\CommandSpec;
use InvalidArgumentException;

/**
 * Builds command line arguments for Pi CLI
 */
final class PiCommandBuilder
{
    use BuildsCliArgv;

    /**
     * Build command spec for headless execution via `pi --mode json`
     */
    public function build(PiRequest $request): CommandSpec
    {
        $this->validate($request);

        $argv = $this->baseArgv(['pi']);

        $argv = $this->appendMode($argv, $request->outputMode());
        $argv = $this->appendModel($argv, $request->model());
        $argv = $this->appendProvider($argv, $request->provider());
        $argv = $this->appendThinking($argv, $request->thinkingLevel());
        $argv = $this->appendSystemPrompt($argv, $request->systemPrompt());
        $argv = $this->appendAppendSystemPrompt($argv, $request->appendSystemPrompt());
        $argv = $this->appendTools($argv, $request->tools(), $request->noTools());
        $argv = $this->appendExtensions($argv, $request->extensions(), $request->noExtensions());
        $argv = $this->appendSkills($argv, $request->skills(), $request->noSkills());
        $argv = $this->appendApiKey($argv, $request->apiKey());
        $argv = $this->appendSessionFlags($argv, $request);
        $argv = $this->appendVerbose($argv, $request->verbose());

        // @file arguments go before the prompt
        $argv = $this->appendFiles($argv, $request->files());

        // Prompt is the final positional argument
        $argv = $argv->with($request->prompt());

        return new CommandSpec($argv, null);
    }

    private function appendMode(Argv $argv, OutputMode $mode): Argv
    {
        return $argv
            ->with('--mode')
            ->with($mode->value);
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

    private function appendProvider(Argv $argv, ?string $provider): Argv
    {
        if ($provider === null || $provider === '') {
            return $argv;
        }

        return $argv
            ->with('--provider')
            ->with($provider);
    }

    private function appendThinking(Argv $argv, ?ThinkingLevel $level): Argv
    {
        if ($level === null) {
            return $argv;
        }

        return $argv
            ->with('--thinking')
            ->with($level->value);
    }

    private function appendSystemPrompt(Argv $argv, ?string $prompt): Argv
    {
        if ($prompt === null || $prompt === '') {
            return $argv;
        }

        return $argv
            ->with('--system-prompt')
            ->with($prompt);
    }

    private function appendAppendSystemPrompt(Argv $argv, ?string $prompt): Argv
    {
        if ($prompt === null || $prompt === '') {
            return $argv;
        }

        return $argv
            ->with('--append-system-prompt')
            ->with($prompt);
    }

    /**
     * @param list<string>|null $tools
     */
    private function appendTools(Argv $argv, ?array $tools, bool $noTools): Argv
    {
        if ($noTools) {
            return $argv->with('--no-tools');
        }

        if ($tools === null || count($tools) === 0) {
            return $argv;
        }

        return $argv
            ->with('--tools')
            ->with(implode(',', $tools));
    }

    /**
     * @param list<string>|null $extensions
     */
    private function appendExtensions(Argv $argv, ?array $extensions, bool $noExtensions): Argv
    {
        if ($noExtensions) {
            return $argv->with('--no-extensions');
        }

        if ($extensions === null || count($extensions) === 0) {
            return $argv;
        }

        $current = $argv;
        foreach ($extensions as $extension) {
            $current = $current
                ->with('-e')
                ->with($extension);
        }

        return $current;
    }

    /**
     * @param list<string>|null $skills
     */
    private function appendSkills(Argv $argv, ?array $skills, bool $noSkills): Argv
    {
        if ($noSkills) {
            return $argv->with('--no-skills');
        }

        if ($skills === null || count($skills) === 0) {
            return $argv;
        }

        $current = $argv;
        foreach ($skills as $skill) {
            $current = $current
                ->with('--skill')
                ->with($skill);
        }

        return $current;
    }

    private function appendApiKey(Argv $argv, ?string $apiKey): Argv
    {
        if ($apiKey === null || $apiKey === '') {
            return $argv;
        }

        return $argv
            ->with('--api-key')
            ->with($apiKey);
    }

    private function appendSessionFlags(Argv $argv, PiRequest $request): Argv
    {
        if ($request->noSession()) {
            return $argv->with('--no-session');
        }

        $sessionDir = $request->sessionDir();
        if ($sessionDir !== null && $sessionDir !== '') {
            $argv = $argv
                ->with('--session-dir')
                ->with($sessionDir);
        }

        if ($request->continueSession()) {
            return $argv->with('--continue');
        }

        $sessionId = $request->sessionId();
        if ($sessionId !== null) {
            return $argv
                ->with('--session')
                ->with($sessionId->toString());
        }

        return $argv;
    }

    private function appendVerbose(Argv $argv, bool $verbose): Argv
    {
        if (!$verbose) {
            return $argv;
        }

        return $argv->with('--verbose');
    }

    /**
     * @param list<string>|null $files
     */
    private function appendFiles(Argv $argv, ?array $files): Argv
    {
        if ($files === null || count($files) === 0) {
            return $argv;
        }

        $current = $argv;
        foreach ($files as $file) {
            $current = $current->with('@' . $file);
        }

        return $current;
    }

    private function validate(PiRequest $request): void
    {
        if (trim($request->prompt()) === '') {
            throw new InvalidArgumentException('Prompt must not be empty');
        }

        CliArgumentValidator::validateModel($request->model());
        CliArgumentValidator::validateExistingFiles($request->files(), 'files');

        $sessionId = $request->sessionId();
        $sessionValue = $sessionId !== null ? $sessionId->toString() : null;
        CliArgumentValidator::validateSessionId($sessionValue, 'sessionId');

        if ($request->continueSession() && $request->sessionId() !== null) {
            throw new InvalidArgumentException(
                'Cannot set both continueSession and sessionId'
            );
        }

        if ($request->noSession() && $request->isResume()) {
            throw new InvalidArgumentException(
                'Cannot use noSession with session continuation'
            );
        }

        if ($request->noTools() && $request->tools() !== null && count($request->tools()) > 0) {
            throw new InvalidArgumentException(
                'Cannot set both noTools and tools list'
            );
        }
    }
}
