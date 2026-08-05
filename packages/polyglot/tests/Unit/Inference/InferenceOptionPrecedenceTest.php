<?php declare(strict_types=1);

use Cognesy\Polyglot\Inference\Creation\InferenceRequestBuilder;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Inference;

/**
 * Pins the option-precedence rule that the DECISION on instructor-eexl.14 rests on
 * (see research/plans/polyglot-improvements/06-request-builder.md, Part B).
 *
 * `withStreaming()` / `withMaxTokens()` are NOT request fields. `InferenceRequest` has no
 * `$streaming` and no `$maxTokens`; they are builder state that `create()` folds into the
 * options array at the end, via `override()`, which skips nulls. The consequence is that on
 * the BUILDER the dedicated setter wins over a conflicting `withOptions()` key regardless of
 * call order, while on `InferenceRequest` -- whose `withStreaming()` writes `$options['stream']`
 * eagerly and whose `with()` does an `array_merge` -- the last writer wins instead.
 *
 * That divergence is the reason Part B was declined: collapsing the builder into the request's
 * withers would silently flip the public facade from order-independent to order-dependent while
 * leaving every method signature identical, so a signature-level "public API unchanged" check
 * would not notice. Nothing else in the suite pins it -- the existing builder test only sets
 * each field once, where both semantics agree.
 */

it('lets withStreaming win over a conflicting options key in either order', function () {
    $streamingFirst = (new InferenceRequestBuilder)
        ->withStreaming(true)
        ->withOptions(['stream' => false])
        ->create();
    $optionsFirst = (new InferenceRequestBuilder)
        ->withOptions(['stream' => false])
        ->withStreaming(true)
        ->create();

    expect($streamingFirst->options()['stream'])->toBeTrue();
    expect($optionsFirst->options()['stream'])->toBeTrue();
});

it('lets withMaxTokens win over a conflicting options key in either order', function () {
    $maxTokensFirst = (new InferenceRequestBuilder)
        ->withMaxTokens(512)
        ->withOptions(['max_tokens' => 99])
        ->create();
    $optionsFirst = (new InferenceRequestBuilder)
        ->withOptions(['max_tokens' => 99])
        ->withMaxTokens(512)
        ->create();

    expect($maxTokensFirst->options()['max_tokens'])->toBe(512);
    expect($optionsFirst->options()['max_tokens'])->toBe(512);
});

it('leaves the option untouched when the dedicated setter is never called', function () {
    // override() skips nulls -- this is what makes the precedence a real rule rather than an
    // unconditional clobber. Without it the builder would force `stream` onto every request.
    $req = (new InferenceRequestBuilder)->withOptions(['stream' => false])->create();

    expect($req->options()['stream'])->toBeFalse();
    expect($req->options())->not->toHaveKey('max_tokens');
});

it('exposes the order-independent semantics through the public Inference facade', function () {
    // The facade forwards to the builder via HandlesRequestBuilder, so this is the behaviour
    // user code actually observes. Part B would have changed this line's expected value.
    $options = static function (Inference $inference): array {
        $property = new ReflectionProperty(Inference::class, 'requestBuilder');

        return $property->getValue($inference)->create()->options();
    };

    expect($options((new Inference)->withStreaming(true)->withOptions(['stream' => false])))
        ->toHaveKey('stream', true);
    expect($options((new Inference)->withOptions(['stream' => false])->withStreaming(true)))
        ->toHaveKey('stream', true);
});

it('documents that InferenceRequest itself is order-dependent, unlike the builder', function () {
    // Not an endorsement -- this is the semantics Part B would have promoted to the facade.
    // Pinned so that anyone who later tries to unify the two layers sees the conflict fail
    // here rather than discovering it from a user bug report.
    $streamingFirst = (new InferenceRequest)->withStreaming(true)->withOptions(['stream' => false]);
    $optionsFirst = (new InferenceRequest)->withOptions(['stream' => false])->withStreaming(true);

    expect($streamingFirst->options()['stream'])->toBeFalse();
    expect($optionsFirst->options()['stream'])->toBeTrue();
});
