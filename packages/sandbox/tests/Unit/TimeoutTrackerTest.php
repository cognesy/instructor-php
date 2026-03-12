<?php declare(strict_types=1);

namespace Cognesy\Sandbox\Tests\Unit;

use Cognesy\Sandbox\Enums\TimeoutReason;
use Cognesy\Sandbox\Utils\TimeoutTracker;

describe('TimeoutTracker', function () {
    it('does not terminate before wall deadline', function () {
        $tracker = new TimeoutTracker(wallSeconds: 60);
        $tracker->start();

        expect($tracker->shouldTerminate())->toBeFalse();
        expect($tracker->timedOut())->toBeFalse();
        expect($tracker->reason())->toBeNull();
    });

    it('terminates after wall deadline', function () {
        $tracker = new TimeoutTracker(wallSeconds: 1);
        $tracker->start();

        // Force past deadline by using reflection to backdate startedAt
        $ref = new \ReflectionProperty($tracker, 'wallDeadline');
        $ref->setValue($tracker, microtime(true) - 1.0);

        expect($tracker->shouldTerminate())->toBeTrue();
        expect($tracker->timedOut())->toBeTrue();
        expect($tracker->reason())->toBe(TimeoutReason::WALL);
    });

    it('terminates on idle timeout', function () {
        $tracker = new TimeoutTracker(wallSeconds: 60, idleSeconds: 1);
        $tracker->start();

        // Backdate last activity
        $ref = new \ReflectionProperty($tracker, 'lastActivityAt');
        $ref->setValue($tracker, microtime(true) - 2.0);

        expect($tracker->shouldTerminate())->toBeTrue();
        expect($tracker->reason())->toBe(TimeoutReason::IDLE);
    });

    it('resets idle timer on activity', function () {
        $tracker = new TimeoutTracker(wallSeconds: 60, idleSeconds: 1);
        $tracker->start();

        // Backdate then signal activity
        $ref = new \ReflectionProperty($tracker, 'lastActivityAt');
        $ref->setValue($tracker, microtime(true) - 2.0);

        $tracker->onActivity();
        expect($tracker->shouldTerminate())->toBeFalse();
    });

    it('tracks duration since start', function () {
        $tracker = new TimeoutTracker(wallSeconds: 60);
        $tracker->start();

        expect($tracker->duration())->toBeGreaterThanOrEqual(0.0);
        expect($tracker->duration())->toBeLessThan(1.0);
    });
});
