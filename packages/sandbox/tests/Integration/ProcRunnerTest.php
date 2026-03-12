<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Integration;

use Cognesy\Sandbox\Runners\ProcRunner;
use Cognesy\Sandbox\Utils\TimeoutTracker;

describe('ProcRunner', function () {
    it('passes stdin payload to child process', function () {
        $runner = new ProcRunner(
            tracker: new TimeoutTracker(wallSeconds: 5),
            stdoutCap: 1024 * 1024,
            stderrCap: 1024 * 1024,
            nameForError: 'proc-runner-test',
        );

        $result = $runner->run(
            argv: ['/bin/cat'],
            cwd: sys_get_temp_dir(),
            env: [],
            stdin: "hello\n",
        );

        expect($result->exitCode())->toBe(0);
        expect($result->stderr())->toBe('');
        expect($result->stdout())->toBe("hello\n");
    });

    it('handles null and empty stdin without bad file descriptor warnings', function (?string $stdin) {
        $runner = new ProcRunner(
            tracker: new TimeoutTracker(wallSeconds: 5),
            stdoutCap: 1024 * 1024,
            stderrCap: 1024 * 1024,
            nameForError: 'proc-runner-test',
        );

        $chunks = [];
        $result = $runner->run(
            argv: ['/bin/cat'],
            cwd: sys_get_temp_dir(),
            env: [],
            stdin: $stdin,
            onOutput: function (string $type, string $chunk) use (&$chunks): void {
                $chunks[] = $type . ':' . $chunk;
            },
        );

        expect($result->exitCode())->toBe(0);
        expect($result->stderr())->toBe('');
        expect(stripos($result->stderr(), 'Bad file descriptor'))->toBeFalse();
        expect($chunks)->toBeArray();
    })->with([
        'null-stdin' => [null],
        'empty-stdin' => [''],
    ]);
});
