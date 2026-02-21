<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Collections\InferenceAttemptList;
use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceAttemptId;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;
use Cognesy\Polyglot\Inference\Data\Usage;

it('constructs list via of() and aggregates usage', function () {
    $a1 = InferenceAttempt::fromResponse(new InferenceResponse(usage: new Usage(inputTokens: 1, outputTokens: 2)));
    $a2 = InferenceAttempt::fromResponse(new InferenceResponse(usage: new Usage(inputTokens: 3, outputTokens: 4)));

    $list = InferenceAttemptList::of($a1, $a2);

    expect($list->count())->toBe(2)
        ->and($list->first()->id)->toBe($a1->id)
        ->and($list->last()->id)->toBe($a2->id)
        ->and($list->usage()->input())->toBe(4)
        ->and($list->usage()->output())->toBe(6)
        ->and($list->usage()->total())->toBe(10);
});

it('rehydrates from array and preserves usage', function () {
    $a1 = InferenceAttempt::fromResponse(new InferenceResponse(usage: new Usage(inputTokens: 2, outputTokens: 1)));
    $a2 = InferenceAttempt::fromResponse(new InferenceResponse(usage: new Usage(inputTokens: 5, outputTokens: 7)));

    $original = InferenceAttemptList::of($a1, $a2);
    $rehydrated = InferenceAttemptList::fromArray($original->toArray());

    expect($rehydrated->count())->toBe(2)
        ->and($rehydrated->first()->id)->toBeInstanceOf(InferenceAttemptId::class)
        ->and($rehydrated->first()->id->toString())->toBe($a1->id->toString())
        ->and($rehydrated->usage()->input())->toBe(7)
        ->and($rehydrated->usage()->output())->toBe(8)
        ->and($rehydrated->usage()->total())->toBe(15);
});
