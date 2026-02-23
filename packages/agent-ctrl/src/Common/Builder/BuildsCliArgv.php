<?php declare(strict_types=1);

namespace Cognesy\AgentCtrl\Common\Builder;

use Cognesy\Sandbox\Value\Argv;
use Cognesy\Sandbox\Utils\ProcUtils;

trait BuildsCliArgv
{
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
