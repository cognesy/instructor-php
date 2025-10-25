<?php declare(strict_types=1);

namespace Cognesy\Utils\Sandbox\Utils;

final class ProcUtils
{
    public static function findSetSidPath(): ?string {
        $c = ['/usr/bin/setsid', '/bin/setsid'];
        foreach ($c as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Locate an executable on PATH and optional extra directories.
     *
     * @param list<string> $extraDirs
     */
    public static function findOnPath(string $binaryName, array $extraDirs = []): ?string {
        $candidates = [];
        $path = getenv('PATH') ?: '';
        if ($path !== '') {
            foreach (explode(PATH_SEPARATOR, $path) as $dir) {
                if ($dir === '') {
                    continue;
                }
                $candidates[] = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binaryName;
            }
        }
        foreach ($extraDirs as $dir) {
            $candidates[] = rtrim($dir, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . $binaryName;
        }
        if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
            $more = [];
            foreach ($candidates as $p) {
                $more[] = $p . '.exe';
            }
            $candidates = array_merge($candidates, $more);
        }
        foreach ($candidates as $p) {
            if (is_executable($p)) {
                return $p;
            }
        }
        return null;
    }

    /**
     * Best-effort termination of a process group (if available) and the process itself.
     * Sends SIGTERM, waits briefly, then SIGKILL if still running.
     * Works on Linux/Unix; on other systems falls back to proc_terminate only.
     *
     * @param resource|object $proc proc_open handle
     */
    public static function terminateProcessGroup($proc, int $pid): void {
        if ($pid > 0 && function_exists('posix_kill')) {
            @posix_kill(-$pid, 15);
        }
        @proc_terminate($proc, 15);
        usleep(100_000);
        $status = proc_get_status($proc);
        if (!is_array($status) || !$status['running']) {
            return;
        }
        if ($pid > 0 && function_exists('posix_kill')) {
            @posix_kill(-$pid, 9);
        }
        @proc_terminate($proc, 9);
    }

    public static function defaultBinPaths(): array {
        return ['/usr/bin', '/usr/local/bin', '/opt/homebrew/bin', '/opt/local/bin', '/snap/bin'];
    }
}
