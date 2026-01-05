<?php declare(strict_types=1);

namespace Tests\Addons\Unit\Core;

use Cognesy\Addons\StepByStep\Continuation\ContinuationEvaluator;

it('returns false when no criteria provided', function () {
    $evaluator = ContinuationEvaluator::with();

    expect($evaluator->isEmpty())->toBeTrue();
    expect($evaluator->canContinue(new \stdClass()))->toBeFalse();
});

it('short circuits on failing criterion', function () {
    $calls = [];
    $evaluator = ContinuationEvaluator::with(
        function (object $state) use (&$calls): bool {
            $calls[] = 'first';
            return false;
        },
        function (object $state) use (&$calls): bool {
            $calls[] = 'second';
            return true;
        },
    );

    expect($evaluator->canContinue(new \stdClass()))->toBeFalse();
    expect($calls)->toBe(['first']);
});

it('remains true when all criteria pass', function () {
    $evaluator = ContinuationEvaluator::with(
        fn(object $state): bool => true,
        fn(object $state): bool => true,
    );

    expect($evaluator->canContinue(new \stdClass()))->toBeTrue();
});

it('creates new evaluator when adding criteria', function () {
    $initial = ContinuationEvaluator::with(fn(object $state): bool => true);
    $extended = $initial->withAdded(fn(object $state): bool => true);

    expect($initial)->not()->toBe($extended);
    expect($extended->canContinue(new \stdClass()))->toBeTrue();
});
