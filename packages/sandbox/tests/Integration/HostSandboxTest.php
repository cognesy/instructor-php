<?php

declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Integration;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Drivers\HostSandbox;

describe('HostSandbox', function () {
    it('retains a bounded final tail when command output exceeds the cap', function () {
        $sandbox = new HostSandbox(
            ExecutionPolicy::in(sys_get_temp_dir())
                ->withOutputCaps(stdoutBytes: 1024, stderrBytes: 1024),
        );

        $result = $sandbox->execute([
            PHP_BINARY,
            '-r',
            'echo str_repeat("x", 2048), "FINAL_TAIL";',
        ]);

        expect($result->success())->toBeTrue();
        expect($result->truncatedStdout())->toBeTrue();
        expect(strlen($result->stdout()))->toBe(1024);
        expect($result->stdout())->toEndWith('FINAL_TAIL');
    });

    it('reports a missing command as a failed execution result', function () {
        $sandbox = new HostSandbox(ExecutionPolicy::in(sys_get_temp_dir()));

        $result = $sandbox->execute(['__missing_command__']);

        expect($result->success())->toBeFalse()
            ->and($result->exitCode())->toBe(127)
            ->and($result->stderr())->toContain('Failed to start host process');
    });
});
