<?php

use Cognesy\Pipeline\Pipeline;

describe('FailWhen functionality', function () {
    test('fails pipeline when condition is met', function () {
        $pipeline = Pipeline::for(10)
            ->failWhen(fn($state) => $state->value() > 5, 'Value too large')
            ->through(fn($x) => $x * 2)
            ->create();

        expect($pipeline->isFailure())->toBeTrue();
        expect($pipeline->exception()->getMessage())->toBe('Value too large');
    });

    test('continues processing when condition is not met', function () {
        $pipeline = Pipeline::for(3)
            ->failWhen(fn($state) => $state->value() > 5, 'Value too large')
            ->through(fn($x) => $x * 2)
            ->create();

        expect($pipeline->isSuccess())->toBeTrue();
        expect($pipeline->value())->toBe(6);
    });

    test('fails with default message when no message provided', function () {
        $pipeline = Pipeline::for(10)
            ->failWhen(fn($state) => $state->value() > 5)
            ->through(fn($x) => $x * 2)
            ->create();

        expect($pipeline->isFailure())->toBeTrue();
        expect($pipeline->exception()->getMessage())->toBe('Condition failed');
    });

    test('can chain multiple failWhen conditions', function () {
        $pipeline = Pipeline::for(10)
            ->failWhen(fn($state) => $state->value() < 0, 'Value too small')
            ->failWhen(fn($state) => $state->value() > 20, 'Value too large')
            ->through(fn($x) => $x * 2)
            ->create();

        expect($pipeline->isSuccess())->toBeTrue();
        expect($pipeline->value())->toBe(20);
    });

    test('first failing condition stops pipeline', function () {
        $executed = false;
        
        $pipeline = Pipeline::for(10)
            ->failWhen(fn($state) => $state->value() > 5, 'First failure')
            ->failWhen(fn($state) => $state->value() > 15, 'Second failure')
            ->through(function($x) use (&$executed) {
                $executed = true;
                return $x * 2;
            })
            ->create();

        expect($pipeline->isFailure())->toBeTrue();
        expect($pipeline->exception()->getMessage())->toBe('First failure');
        expect($executed)->toBeFalse();
    });
});