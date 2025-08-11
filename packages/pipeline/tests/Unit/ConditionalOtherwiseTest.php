<?php declare(strict_types=1);

use Cognesy\Pipeline\Pipeline;

describe('ConditionalCall otherwise branch via builder', function () {
    it('applies then when condition is true', function () {
        $value = Pipeline::for(5)
            ->when(fn($x) => $x > 0, fn($x) => $x + 10, fn($x) => $x - 10)
            ->create()
            ->value();

        expect($value)->toBe(15);
    });

    it('applies otherwise when condition is false', function () {
        $value = Pipeline::for(0)
            ->when(fn($x) => $x > 0, fn($x) => $x + 10, fn($x) => $x - 10)
            ->create()
            ->value();

        expect($value)->toBe(-10);
    });
});

