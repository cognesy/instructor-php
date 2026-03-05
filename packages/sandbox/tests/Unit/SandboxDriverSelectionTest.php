<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Drivers\BubblewrapSandbox;
use Cognesy\Sandbox\Drivers\DockerSandbox;
use Cognesy\Sandbox\Drivers\FirejailSandbox;
use Cognesy\Sandbox\Drivers\HostSandbox;
use Cognesy\Sandbox\Drivers\PodmanSandbox;
use Cognesy\Sandbox\Enums\SandboxDriver;
use Cognesy\Sandbox\Sandbox;
use InvalidArgumentException;

describe('Sandbox driver selection', function () {
    it('throws explicit exception for invalid driver string', function () {
        $sandbox = Sandbox::fromPolicy(ExecutionPolicy::in(sys_get_temp_dir()));

        expect(fn() => $sandbox->using('not-a-driver'))
            ->toThrow(InvalidArgumentException::class, 'Unsupported sandbox driver: not-a-driver');
    });

    it('supports enum-safe driver selection', function (SandboxDriver $driver, string $expectedClass) {
        $sandbox = Sandbox::fromPolicy(ExecutionPolicy::in(sys_get_temp_dir()));

        $selected = $sandbox->using($driver);

        expect($selected)->toBeInstanceOf($expectedClass);
    })->with([
        'host' => [SandboxDriver::Host, HostSandbox::class],
        'docker' => [SandboxDriver::Docker, DockerSandbox::class],
        'podman' => [SandboxDriver::Podman, PodmanSandbox::class],
        'firejail' => [SandboxDriver::Firejail, FirejailSandbox::class],
        'bubblewrap' => [SandboxDriver::Bubblewrap, BubblewrapSandbox::class],
    ]);
});

