<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\OpenCode\Application\Builder;

use Cognesy\Sandbox\Value\Argv;
use Cognesy\Sandbox\Value\CommandSpec;
use Cognesy\AgentCtrl\OpenCode\Application\Dto\OpenCodeRequest;
use Cognesy\AgentCtrl\OpenCode\Domain\Enum\OutputFormat;
use Cognesy\Sandbox\Utils\ProcUtils;
use InvalidArgumentException;

/**
 * Builds command line arguments for OpenCode CLI run command
 */
final class OpenCodeCommandBuilder
{
    /**
     * Build command spec for headless execution via `opencode run`
     */
    public function buildRun(OpenCodeRequest $request): CommandSpec
    {
        $this->validate($request);

        $argv = $this->baseArgv(['opencode', 'run']);

        // Add flags before prompt
        $argv = $this->appendOutputFormat($argv, $request->outputFormat());
        $argv = $this->appendModel($argv, $request->modelString());
        $argv = $this->appendAgent($argv, $request->agent());
        $argv = $this->appendFiles($argv, $request->files());
        $argv = $this->appendSessionFlags($argv, $request);
        $argv = $this->appendShare($argv, $request->share());
        $argv = $this->appendTitle($argv, $request->title());
        $argv = $this->appendAttach($argv, $request->attachUrl());
        $argv = $this->appendPort($argv, $request->port());
        $argv = $this->appendCommand($argv, $request->command());

        // Prompt goes last as positional argument
        $argv = $argv->with($request->prompt());

        return new CommandSpec($argv, null);
    }

    private function appendOutputFormat(Argv $argv, OutputFormat $format): Argv
    {
        if ($format === OutputFormat::Default) {
            return $argv;
        }

        return $argv
            ->with('--format')
            ->with($format->value);
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

    private function appendAgent(Argv $argv, ?string $agent): Argv
    {
        if ($agent === null || $agent === '') {
            return $argv;
        }

        return $argv
            ->with('--agent')
            ->with($agent);
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
            $current = $current
                ->with('--file')
                ->with($file);
        }

        return $current;
    }

    private function appendSessionFlags(Argv $argv, OpenCodeRequest $request): Argv
    {
        if ($request->continueSession()) {
            return $argv->with('--continue');
        }

        $sessionId = $request->sessionId();
        if ($sessionId !== null && $sessionId !== '') {
            return $argv
                ->with('--session')
                ->with($sessionId);
        }

        return $argv;
    }

    private function appendShare(Argv $argv, bool $share): Argv
    {
        if (!$share) {
            return $argv;
        }

        return $argv->with('--share');
    }

    private function appendTitle(Argv $argv, ?string $title): Argv
    {
        if ($title === null || $title === '') {
            return $argv;
        }

        return $argv
            ->with('--title')
            ->with($title);
    }

    private function appendAttach(Argv $argv, ?string $url): Argv
    {
        if ($url === null || $url === '') {
            return $argv;
        }

        return $argv
            ->with('--attach')
            ->with($url);
    }

    private function appendPort(Argv $argv, ?int $port): Argv
    {
        if ($port === null) {
            return $argv;
        }

        return $argv
            ->with('--port')
            ->with((string) $port);
    }

    private function appendCommand(Argv $argv, ?string $command): Argv
    {
        if ($command === null || $command === '') {
            return $argv;
        }

        return $argv
            ->with('--command')
            ->with($command);
    }

    private function validate(OpenCodeRequest $request): void
    {
        if (trim($request->prompt()) === '') {
            throw new InvalidArgumentException('Prompt must not be empty');
        }

        if ($request->continueSession() && $request->sessionId() !== null) {
            throw new InvalidArgumentException(
                'Cannot set both continueSession and sessionId'
            );
        }
    }

    /**
     * @param list<string> $command
     */
    private function baseArgv(array $command): Argv
    {
        $prefix = $this->stdbufPrefix();
        return match (true) {
            $prefix === null => Argv::of($command),
            default => Argv::of(array_merge($prefix, $command)),
        };
    }

    /**
     * @return list<string>|null
     */
    private function stdbufPrefix(): ?array
    {
        $override = getenv('COGNESY_STDBUF');
        return match (true) {
            $override === '0' => null,
            $override === '1' => ['stdbuf', '-o0'],
            $this->isWindows() => null,
            $this->isStdbufAvailable() => ['stdbuf', '-o0'],
            default => null,
        };
    }

    private function isStdbufAvailable(): bool
    {
        return ProcUtils::findOnPath('stdbuf', ProcUtils::defaultBinPaths()) !== null;
    }

    private function isWindows(): bool
    {
        return str_starts_with(strtoupper(PHP_OS), 'WIN');
    }
}
