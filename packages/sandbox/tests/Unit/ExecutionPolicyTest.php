<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Config\ExecutionPolicy;
use InvalidArgumentException;

describe('ExecutionPolicy', function () {
    it('has sensible defaults', function () {
        $policy = ExecutionPolicy::default();

        expect($policy->baseDir())->toBe('/tmp');
        expect($policy->timeoutSeconds())->toBe(5);
        expect($policy->idleTimeoutSeconds())->toBeNull();
        expect($policy->memoryLimit())->toBe('128M');
        expect($policy->inheritEnv())->toBeFalse();
        expect($policy->networkEnabled())->toBeFalse();
    });

    it('clamps timeout to minimum of 1 second', function () {
        $policy = ExecutionPolicy::default()->withTimeout(0);
        expect($policy->timeoutSeconds())->toBe(1);

        $policy = ExecutionPolicy::default()->withTimeout(-5);
        expect($policy->timeoutSeconds())->toBe(1);
    });

    it('clamps output caps to minimum of 1024 bytes', function () {
        $policy = ExecutionPolicy::default()->withOutputCaps(100, 50);
        expect($policy->stdoutLimitBytes())->toBe(1024);
        expect($policy->stderrLimitBytes())->toBe(1024);
    });

    it('normalizes memory limit to megabytes', function () {
        expect(ExecutionPolicy::default()->withMemory('256M')->memoryLimit())->toBe('256M');
        expect(ExecutionPolicy::default()->withMemory('1G')->memoryLimit())->toBe('1024M');
        expect(ExecutionPolicy::default()->withMemory('512k')->memoryLimit())->toBe('1M');
    });

    it('clamps memory to 1G maximum', function () {
        $policy = ExecutionPolicy::default()->withMemory('2G');
        expect($policy->memoryLimit())->toBe('1024M');
    });

    it('rejects unbounded memory limit', function () {
        expect(fn() => ExecutionPolicy::default()->withMemory('-1'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('rejects invalid memory format', function () {
        expect(fn() => ExecutionPolicy::default()->withMemory('abc'))
            ->toThrow(InvalidArgumentException::class);
    });

    it('returns new instance from withers preserving other fields', function () {
        $a = ExecutionPolicy::in('/work')->withTimeout(10)->withNetwork(true);
        $b = $a->withMemory('256M');

        expect($b)->not->toBe($a);
        expect($b->baseDir())->toBe('/work');
        expect($b->timeoutSeconds())->toBe(10);
        expect($b->networkEnabled())->toBeTrue();
        expect($b->memoryLimit())->toBe('256M');
    });
});
