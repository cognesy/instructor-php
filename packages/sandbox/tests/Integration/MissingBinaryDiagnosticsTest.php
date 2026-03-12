<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Integration;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Contracts\CanExecuteCommand;
use Cognesy\Sandbox\Drivers\BubblewrapSandbox;
use Cognesy\Sandbox\Drivers\DockerSandbox;
use Cognesy\Sandbox\Drivers\FirejailSandbox;
use Cognesy\Sandbox\Drivers\PodmanSandbox;

it('reports consistent startup diagnostics for empty binary configuration', function (
    callable $makeDriver,
    string $expectedError
) {
    $baseDir = sys_get_temp_dir() . '/sandbox-empty-bin-' . bin2hex(random_bytes(6));
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

        expect(fn() => $driver->execute(['/bin/echo', 'ok']))
            ->toThrow(\RuntimeException::class, $expectedError);
    } finally {
        restore_error_handler();
    }
})->with([
    'docker' => [fn(ExecutionPolicy $policy): CanExecuteCommand => new DockerSandbox($policy, 'alpine:3', ''), 'Failed to start docker'],
    'podman' => [fn(ExecutionPolicy $policy): CanExecuteCommand => new PodmanSandbox($policy, 'alpine:3', ''), 'Failed to start podman'],
    'firejail' => [fn(ExecutionPolicy $policy): CanExecuteCommand => new FirejailSandbox($policy, ''), 'Failed to start firejail'],
    'bubblewrap' => [fn(ExecutionPolicy $policy): CanExecuteCommand => new BubblewrapSandbox($policy, ''), 'Failed to start bwrap'],
]);
