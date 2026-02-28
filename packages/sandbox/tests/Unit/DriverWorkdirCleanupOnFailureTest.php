<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Drivers\BubblewrapSandbox;
use Cognesy\Sandbox\Drivers\DockerSandbox;
use Cognesy\Sandbox\Drivers\FirejailSandbox;
use Cognesy\Sandbox\Drivers\PodmanSandbox;

function cleanupRemoveTree(string $path): void
{
    if ($path === '' || !file_exists($path)) {
        return;
    }
    if (is_link($path) || is_file($path)) {
        @unlink($path);
        return;
    }
    $items = scandir($path);
    if ($items === false) {
        return;
    }
    foreach ($items as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }
        cleanupRemoveTree($path . DIRECTORY_SEPARATOR . $item);
    }
    @rmdir($path);
}

/** @return list<string> */
function leakedSandboxDirs(string $baseDir): array
{
    if (!is_dir($baseDir)) {
        return [];
    }
    $items = scandir($baseDir);
    if ($items === false) {
        return [];
    }
    $dirs = [];
    foreach ($items as $item) {
        if (!str_starts_with($item, 'sandbox-')) {
            continue;
        }
        if (!is_dir($baseDir . DIRECTORY_SEPARATOR . $item)) {
            continue;
        }
        $dirs[] = $item;
    }
    return $dirs;
}

describe('Sandbox drivers cleanup', function () {
    it('removes workdir when launch fails', function (callable $makeDriver) {
        $baseDir = sys_get_temp_dir() . '/sandbox-driver-cleanup-' . bin2hex(random_bytes(6));
        @mkdir($baseDir, 0o700, true);
        $policy = ExecutionPolicy::in($baseDir);
        /** @var CanExecuteCommand $driver */
        $driver = $makeDriver($policy);

        try {
            set_error_handler(static function (int $severity, string $message): bool {
                if ($severity === E_WARNING && str_contains($message, 'proc_open()')) {
                    return true;
                }
                return false;
            });
            expect(fn() => $driver->execute(['/bin/echo', 'ok']))->toThrow(\RuntimeException::class);
            expect(leakedSandboxDirs($baseDir))->toBe([]);
        } finally {
            restore_error_handler();
            cleanupRemoveTree($baseDir);
        }
    })->with([
        'docker' => [fn(ExecutionPolicy $policy): CanExecuteCommand => new DockerSandbox($policy, 'alpine:3', '/definitely-not-a-real-binary')],
        'podman' => [fn(ExecutionPolicy $policy): CanExecuteCommand => new PodmanSandbox($policy, 'alpine:3', '/definitely-not-a-real-binary')],
        'firejail' => [fn(ExecutionPolicy $policy): CanExecuteCommand => new FirejailSandbox($policy, '/definitely-not-a-real-binary')],
        'bubblewrap' => [fn(ExecutionPolicy $policy): CanExecuteCommand => new BubblewrapSandbox($policy, '/definitely-not-a-real-binary')],
    ]);
});
