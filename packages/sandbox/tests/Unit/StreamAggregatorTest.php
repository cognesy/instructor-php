<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Utils\StreamAggregator;
use Symfony\Component\Process\Process;

describe('StreamAggregator', function () {
    it('accumulates stdout and stderr separately', function () {
        $agg = new StreamAggregator(1024, 1024);
        $agg->appendOut('hello ');
        $agg->appendOut('world');
        $agg->appendErr('warning');

        expect($agg->stdout())->toBe('hello world');
        expect($agg->stderr())->toBe('warning');
    });

    it('truncates stdout keeping the tail when cap is exceeded', function () {
        $agg = new StreamAggregator(10, 1024);
        $agg->appendOut('abcdefghij'); // exactly 10 — no truncation yet
        expect($agg->truncatedStdout())->toBeFalse();

        $agg->appendOut('k'); // 11 bytes, triggers truncation to last 10
        expect($agg->truncatedStdout())->toBeTrue();
        expect($agg->stdout())->toBe('bcdefghijk');
    });

    it('truncates stderr keeping the tail when cap is exceeded', function () {
        $agg = new StreamAggregator(1024, 5);
        $agg->appendErr('12345678');

        expect($agg->truncatedStderr())->toBeTrue();
        expect($agg->stderr())->toBe('45678');
    });

    it('stops accumulating after truncation', function () {
        $agg = new StreamAggregator(5, 1024);
        $agg->appendOut('abcdef'); // triggers truncation
        expect($agg->truncatedStdout())->toBeTrue();

        $agg->appendOut('more'); // should be ignored
        expect($agg->stdout())->toBe('bcdef');
    });

    it('routes via consume using Symfony process type constants', function () {
        $agg = new StreamAggregator(1024, 1024);
        $agg->consume(Process::OUT, 'stdout');
        $agg->consume(Process::ERR, 'stderr');

        expect($agg->stdout())->toBe('stdout');
        expect($agg->stderr())->toBe('stderr');
    });
});
