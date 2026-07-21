<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;

/**
 * Regression: option-update semantics were inconsistent across the request
 * construction paths. InferenceRequestBuilder::with() replaced the whole options
 * array while withOptions() merged, and InferenceRequest::withOptions() replaced
 * again. Equivalent-looking chains therefore behaved differently by method and
 * order. The unified rule is: option updates always MERGE into existing options,
 * with later keys overriding earlier ones.
 */

it('merges options consistently across builder with() and withOptions() regardless of order', function () {
    // withOptions then with(options:) — previously yielded only the with() options
    $viaMixed = (new InferenceRequestBuilder())
        ->withMessages(Messages::fromString('hi'))
        ->withOptions(['a' => 1])
        ->with(options: ['b' => 2])
        ->create();

    // two withOptions calls
    $viaWithOptions = (new InferenceRequestBuilder())
        ->withMessages(Messages::fromString('hi'))
        ->withOptions(['a' => 1])
        ->withOptions(['b' => 2])
        ->create();

    expect($viaMixed->options())->toBe(['a' => 1, 'b' => 2])
        ->and($viaWithOptions->options())->toBe(['a' => 1, 'b' => 2]);
});

it('lets later option keys override earlier ones when merging on the builder', function () {
    $req = (new InferenceRequestBuilder())
        ->withMessages(Messages::fromString('hi'))
        ->withOptions(['temperature' => 0.1, 'top_p' => 0.9])
        ->with(options: ['temperature' => 0.7])
        ->create();

    expect($req->options())->toBe(['temperature' => 0.7, 'top_p' => 0.9]);
});

it('merges options on the immutable request for both with() and withOptions()', function () {
    $base = new InferenceRequest(
        messages: Messages::fromString('hi'),
        options: ['a' => 1],
    );

    expect($base->withOptions(['b' => 2])->options())->toBe(['a' => 1, 'b' => 2])
        ->and($base->with(options: ['b' => 2])->options())->toBe(['a' => 1, 'b' => 2])
        // later key wins
        ->and($base->withOptions(['a' => 9])->options())->toBe(['a' => 9]);
});

it('does not mutate the source request when merging options (immutability)', function () {
    $base = new InferenceRequest(
        messages: Messages::fromString('hi'),
        options: ['a' => 1],
    );

    $derived = $base->withOptions(['b' => 2]);

    expect($base->options())->toBe(['a' => 1])
        ->and($derived->options())->toBe(['a' => 1, 'b' => 2])
        ->and($derived->id())->toBe($base->id()) // identity preserved
        ->and($derived)->not->toBe($base);
});
