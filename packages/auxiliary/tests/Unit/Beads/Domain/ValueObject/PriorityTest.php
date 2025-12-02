<?php

declare(strict_types=1);

use Cognesy\Auxiliary\Beads\Domain\ValueObject\Priority;

describe('Priority', function () {
    describe('factory methods', function () {
        it('creates critical priority', function () {
            $priority = Priority::critical();

            expect($priority->value)->toBe(0)
                ->and($priority->label)->toBe('Critical')
                ->and($priority->isCritical())->toBeTrue();
        });

        it('creates high priority', function () {
            $priority = Priority::high();

            expect($priority->value)->toBe(1)
                ->and($priority->label)->toBe('High');
        });

        it('creates medium priority', function () {
            $priority = Priority::medium();

            expect($priority->value)->toBe(2)
                ->and($priority->label)->toBe('Medium');
        });

        it('creates low priority', function () {
            $priority = Priority::low();

            expect($priority->value)->toBe(3)
                ->and($priority->label)->toBe('Low');
        });

        it('creates backlog priority', function () {
            $priority = Priority::backlog();

            expect($priority->value)->toBe(4)
                ->and($priority->label)->toBe('Backlog');
        });
    });

    describe('fromInt factory', function () {
        it('creates priority from valid integer values', function () {
            expect(Priority::fromInt(0))->toEqual(Priority::critical())
                ->and(Priority::fromInt(1))->toEqual(Priority::high())
                ->and(Priority::fromInt(2))->toEqual(Priority::medium())
                ->and(Priority::fromInt(3))->toEqual(Priority::low())
                ->and(Priority::fromInt(4))->toEqual(Priority::backlog());
        });

        it('throws exception for invalid integer values', function () {
            expect(fn() => Priority::fromInt(-1))->toThrow(InvalidArgumentException::class);
            expect(fn() => Priority::fromInt(5))->toThrow(InvalidArgumentException::class);
            expect(fn() => Priority::fromInt(10))->toThrow(InvalidArgumentException::class);
        });

        it('provides helpful error message for invalid values', function () {
            try {
                Priority::fromInt(5);
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException $e) {
                expect($e->getMessage())->toContain('Invalid priority value: 5')
                    ->and($e->getMessage())->toContain('Must be 0-4');
            }
        });
    });

    describe('comparison methods', function () {
        it('correctly identifies critical priority', function () {
            expect(Priority::critical()->isCritical())->toBeTrue()
                ->and(Priority::high()->isCritical())->toBeFalse()
                ->and(Priority::medium()->isCritical())->toBeFalse()
                ->and(Priority::low()->isCritical())->toBeFalse()
                ->and(Priority::backlog()->isCritical())->toBeFalse();
        });

        it('compares higher priority correctly', function () {
            $critical = Priority::critical();
            $high = Priority::high();
            $medium = Priority::medium();
            $low = Priority::low();
            $backlog = Priority::backlog();

            // Critical is higher than everything
            expect($critical->isHigherThan($high))->toBeTrue()
                ->and($critical->isHigherThan($medium))->toBeTrue()
                ->and($critical->isHigherThan($low))->toBeTrue()
                ->and($critical->isHigherThan($backlog))->toBeTrue();

            // High is higher than medium, low, backlog but not critical
            expect($high->isHigherThan($critical))->toBeFalse()
                ->and($high->isHigherThan($medium))->toBeTrue()
                ->and($high->isHigherThan($low))->toBeTrue()
                ->and($high->isHigherThan($backlog))->toBeTrue();

            // Same priority is not higher
            expect($medium->isHigherThan($medium))->toBeFalse();
        });

        it('compares lower priority correctly', function () {
            $critical = Priority::critical();
            $high = Priority::high();
            $medium = Priority::medium();
            $low = Priority::low();
            $backlog = Priority::backlog();

            // Backlog is lower than everything
            expect($backlog->isLowerThan($low))->toBeTrue()
                ->and($backlog->isLowerThan($medium))->toBeTrue()
                ->and($backlog->isLowerThan($high))->toBeTrue()
                ->and($backlog->isLowerThan($critical))->toBeTrue();

            // Critical is not lower than anything
            expect($critical->isLowerThan($high))->toBeFalse()
                ->and($critical->isLowerThan($medium))->toBeFalse()
                ->and($critical->isLowerThan($low))->toBeFalse()
                ->and($critical->isLowerThan($backlog))->toBeFalse();

            // Same priority is not lower
            expect($medium->isLowerThan($medium))->toBeFalse();
        });

        it('checks equality correctly', function () {
            expect(Priority::critical()->equals(Priority::critical()))->toBeTrue()
                ->and(Priority::high()->equals(Priority::high()))->toBeTrue()
                ->and(Priority::medium()->equals(Priority::medium()))->toBeTrue()
                ->and(Priority::low()->equals(Priority::low()))->toBeTrue()
                ->and(Priority::backlog()->equals(Priority::backlog()))->toBeTrue();

            expect(Priority::critical()->equals(Priority::high()))->toBeFalse()
                ->and(Priority::high()->equals(Priority::medium()))->toBeFalse()
                ->and(Priority::medium()->equals(Priority::low()))->toBeFalse()
                ->and(Priority::low()->equals(Priority::backlog()))->toBeFalse();
        });
    });

    describe('string representation', function () {
        it('formats string representation correctly', function () {
            expect((string) Priority::critical())->toBe('Critical (0)')
                ->and((string) Priority::high())->toBe('High (1)')
                ->and((string) Priority::medium())->toBe('Medium (2)')
                ->and((string) Priority::low())->toBe('Low (3)')
                ->and((string) Priority::backlog())->toBe('Backlog (4)');
        });
    });

    describe('immutability', function () {
        it('is immutable readonly object', function () {
            $priority = Priority::high();

            // Should be readonly properties
            expect(property_exists($priority, 'value'))->toBeTrue()
                ->and(property_exists($priority, 'label'))->toBeTrue();

            // Values should be accessible
            expect($priority->value)->toBe(1)
                ->and($priority->label)->toBe('High');
        });
    });
});