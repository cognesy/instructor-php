<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Data\ExecResult;

describe('ExecResult', function () {
    it('reports success when exit code is zero and not timed out', function () {
        $result = new ExecResult('ok', '', 0, 0.5);
        expect($result->success())->toBeTrue();
    });

    it('reports failure when exit code is non-zero', function () {
        $result = new ExecResult('', 'err', 1, 0.5);
        expect($result->success())->toBeFalse();
    });

    it('reports failure when timed out even with zero exit code', function () {
        $result = new ExecResult('ok', '', 0, 5.0, timedOut: true);
        expect($result->success())->toBeFalse();
    });

    it('combines stdout and stderr with newline separator', function () {
        $result = new ExecResult('out', 'err', 0, 0.1);
        expect($result->combinedOutput())->toBe("out\nerr");
    });

    it('returns only stdout when stderr is empty', function () {
        $result = new ExecResult('out', '', 0, 0.1);
        expect($result->combinedOutput())->toBe('out');
    });

    it('returns only stderr when stdout is empty', function () {
        $result = new ExecResult('', 'err', 1, 0.1);
        expect($result->combinedOutput())->toBe('err');
    });

    it('serializes all fields via toArray', function () {
        $result = new ExecResult('out', 'err', 42, 1.5, timedOut: true, truncatedStdout: true, truncatedStderr: false);
        $arr = $result->toArray();

        expect($arr)->toBe([
            'stdout' => 'out',
            'stderr' => 'err',
            'exit_code' => 42,
            'duration' => 1.5,
            'timed_out' => true,
            'truncated_stdout' => true,
            'truncated_stderr' => false,
            'success' => false,
        ]);
    });
});
