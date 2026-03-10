<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Utils\ContainerCommandBuilder;

describe('ContainerCommandBuilder', function () {
    it('builds the docker launch command with secure defaults and mounts', function () {
        $cmd = ContainerCommandBuilder::docker('/usr/local/bin/docker')
            ->withImage('php:8.3-cli-alpine')
            ->withPidsLimit(0)
            ->withMemory('256M')
            ->withCpus('1.5')
            ->withUser('1000:1000')
            ->withWorkdir('/workspace')
            ->mountWorkdir('/host/work')
            ->addWritableMount('/host/cache', '/cache')
            ->addReadonlyMount('/host/src', '/src')
            ->withEnv(['APP_ENV' => 'test'])
            ->withInnerArgv(['php', '-v'])
            ->build();

        expect($cmd)->toBe([
            '/usr/local/bin/docker',
            'run',
            '--rm',
            '--network=none',
            '--pids-limit=1',
            '--memory',
            '256m',
            '--cpus',
            '1.5',
            '--read-only',
            '--tmpfs',
            '/tmp:rw,noexec,nodev,nosuid,size=64m',
            '--cap-drop=ALL',
            '--security-opt',
            'no-new-privileges',
            '-u',
            '1000:1000',
            '-v',
            '/host/work:/workspace:rw,bind',
            '-v',
            '/host/cache:/cache:rw,bind',
            '-v',
            '/host/src:/src:ro,bind',
            '-e',
            'APP_ENV=test',
            '-w',
            '/workspace',
            'php:8.3-cli-alpine',
            'php',
            '-v',
        ]);
    });

    it('supports podman compatibility flags without resource limits', function () {
        $cmd = ContainerCommandBuilder::podman('podman')
            ->withImage('alpine:3')
            ->withGlobalFlags(['--cgroup-manager=cgroupfs'])
            ->withResourceLimits(false)
            ->withMemory('2G')
            ->withCpus('2.0')
            ->withInnerArgv(['sh', '-lc', 'echo ok'])
            ->build();

        expect($cmd)->toContain('--cgroup-manager=cgroupfs');
        expect($cmd)->toContain('--pids-limit=20');
        expect($cmd)->not->toContain('--memory');
        expect($cmd)->not->toContain('2g');
        expect($cmd)->not->toContain('--cpus');
        expect($cmd)->toContain('alpine:3');
        expect(array_slice($cmd, -3))->toBe(['sh', '-lc', 'echo ok']);
    });
});
