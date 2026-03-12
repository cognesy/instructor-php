<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Integration;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Drivers\BubblewrapSandbox;
use Cognesy\Sandbox\Drivers\DockerSandbox;
use Cognesy\Sandbox\Drivers\FirejailSandbox;
use Cognesy\Sandbox\Drivers\PodmanSandbox;
use ReflectionMethod;

describe('Readable paths mounts', function () {
    it('adds read-only bind mounts for readablePaths in Bubblewrap command', function () {
        $readablePath = sys_get_temp_dir() . '/sandbox-readable-' . bin2hex(random_bytes(6));
        @mkdir($readablePath, 0o700, true);
        $workDir = sys_get_temp_dir() . '/sandbox-work-' . bin2hex(random_bytes(6));
        @mkdir($workDir, 0o700, true);

        try {
            $policy = ExecutionPolicy::in(sys_get_temp_dir())->withReadablePaths($readablePath);
            $driver = new BubblewrapSandbox($policy, '/bin/echo');
            $method = new ReflectionMethod(BubblewrapSandbox::class, 'buildCommand');
            /** @var list<string> $cmd */
            $cmd = $method->invoke($driver, $workDir, ['/bin/echo', 'ok']);

            $joined = implode("\n", $cmd);
            expect($joined)->toContain('--ro-bind');
            expect($joined)->toContain($readablePath);
        } finally {
            @rmdir($readablePath);
            @rmdir($workDir);
        }
    });

    it('keeps readablePaths command-mount parity across non-host drivers', function () {
        $readablePath = sys_get_temp_dir() . '/sandbox-readable-' . bin2hex(random_bytes(6));
        @mkdir($readablePath, 0o700, true);
        $workDir = sys_get_temp_dir() . '/sandbox-work-' . bin2hex(random_bytes(6));
        @mkdir($workDir, 0o700, true);

        try {
            $policy = ExecutionPolicy::in(sys_get_temp_dir())->withReadablePaths($readablePath);
            $invokePrivate = static function (string $class, string $method, object $object, string $workDir, array $argv): array {
                $ref = new ReflectionMethod($class, $method);
                /** @var list<string> $cmd */
                $cmd = $ref->invoke($object, $workDir, $argv);
                return $cmd;
            };
            $builders = [
                fn() => $invokePrivate(
                    DockerSandbox::class,
                    'buildContainerCommand',
                    new DockerSandbox($policy, 'alpine:3', '/bin/echo'),
                    $workDir,
                    ['/bin/echo', 'ok'],
                ),
                fn() => $invokePrivate(
                    PodmanSandbox::class,
                    'buildContainerCommand',
                    new PodmanSandbox($policy, 'alpine:3', '/bin/echo'),
                    $workDir,
                    ['/bin/echo', 'ok'],
                ),
                fn() => $invokePrivate(
                    FirejailSandbox::class,
                    'buildCommand',
                    new FirejailSandbox($policy, '/bin/echo'),
                    $workDir,
                    ['/bin/echo', 'ok'],
                ),
                fn() => $invokePrivate(
                    BubblewrapSandbox::class,
                    'buildCommand',
                    new BubblewrapSandbox($policy, '/bin/echo'),
                    $workDir,
                    ['/bin/echo', 'ok'],
                ),
            ];

            foreach ($builders as $build) {
                /** @var list<string> $cmd */
                $cmd = $build();
                expect(implode("\n", $cmd))->toContain($readablePath);
            }
        } finally {
            @rmdir($readablePath);
            @rmdir($workDir);
        }
    });
});
