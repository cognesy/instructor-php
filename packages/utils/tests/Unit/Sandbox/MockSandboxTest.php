<?php declare(strict_types=1);

namespace Tests\Utils\Unit\Sandbox;

use Cognesy\Utils\Sandbox\Config\ExecutionPolicy;
use Cognesy\Utils\Sandbox\Data\ExecResult;
use Cognesy\Utils\Sandbox\Testing\MockSandbox;
use RuntimeException;

describe('MockSandbox', function () {
    it('returns queued responses in order', function () {
        $sandbox = new MockSandbox(
            policy: ExecutionPolicy::in('/tmp'),
            responses: [
                'echo hi' => [
                    ['stdout' => 'hi', 'exit_code' => 0],
                    ['stdout' => 'hi again', 'exit_code' => 0],
                ],
            ],
        );

        $first = $sandbox->execute(['echo', 'hi']);
        $second = $sandbox->execute(['echo', 'hi']);

        expect($first->stdout())->toBe('hi');
        expect($second->stdout())->toBe('hi again');
    });

    it('streams stdout and stderr', function () {
        $sandbox = new MockSandbox(
            policy: ExecutionPolicy::in('/tmp'),
            responses: [
                'run' => [
                    ['stdout' => 'out', 'stderr' => 'err', 'exit_code' => 0],
                ],
            ],
        );

        $chunks = [];
        $result = $sandbox->executeStreaming(['run'], function (string $type, string $chunk) use (&$chunks): void {
            $chunks[] = "{$type}:{$chunk}";
        });

        expect($result->success())->toBeTrue();
        expect($chunks)->toBe(['out:out', 'err:err']);
    });

    it('throws when no response is configured', function () {
        $sandbox = new MockSandbox(ExecutionPolicy::in('/tmp'));

        $run = fn() => $sandbox->execute(['missing']);

        expect($run)->toThrow(RuntimeException::class);
    });
});
