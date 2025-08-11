<?php declare(strict_types=1);

use Cognesy\Pipeline\Pipeline;

describe('ConditionalCall otherwise branch via builder', function () {
    it('applies then when condition is true', function () {
        $value = Pipeline::builder()
            ->when(fn($x) => $x > 0, fn($x) => $x + 10, fn($x) => $x - 10)
            ->create()
            ->executeWith(5)
            ->value();

        expect($value)->toBe(15);
    });

    it('applies otherwise when condition is false', function () {
        $value = Pipeline::builder()
            ->when(fn($x) => $x > 0, fn($x) => $x + 10, fn($x) => $x - 10)
            ->create()
            ->executeWith(0)
            ->value();

        expect($value)->toBe(-10);
    });
});

