<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Integration;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Sandbox\Drivers\FirejailSandbox;
use ReflectionMethod;

describe('Firejail security policy', function () {
    it('does not disable profiles in generated command', function () {
        $workDir = sys_get_temp_dir() . '/sandbox-work-' . bin2hex(random_bytes(6));
        @mkdir($workDir, 0o700, true);

        try {
            $policy = ExecutionPolicy::in(sys_get_temp_dir());
            $driver = new FirejailSandbox($policy, '/bin/echo');
            $method = new ReflectionMethod(FirejailSandbox::class, 'buildCommand');
            /** @var list<string> $cmd */
            $cmd = $method->invoke($driver, $workDir, ['/bin/echo', 'ok']);

            expect($cmd)->not->toContain('--noprofile');
        } finally {
            @rmdir($workDir);
        }
    });
});
