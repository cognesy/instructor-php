<?php declare(strict_types=1);

use Cognesy\Messages\Messages;
use Cognesy\Polyglot\Inference\Data\InferenceAttempt;
use Cognesy\Polyglot\Inference\Data\InferenceExecution;
use Cognesy\Polyglot\Inference\Data\InferenceRequest;
use Cognesy\Polyglot\Inference\Data\InferenceResponse;

/**
 * The four Inference data classes used to build a `new DateTimeImmutable` for updatedAt on
 * every with() call. Nothing on any execution path reads updatedAt -- it is consumed only by
 * toArray(), and InferenceRequest::toArray() does not even emit it -- so the clock read and
 * the allocation were paid per copy for nobody.
 *
 * Copies now carry the source object's updatedAt instance over. These tests pin the identity
 * (===, not ==), because an equal-but-distinct DateTimeImmutable would mean the allocation
 * came back.
 *
 * See research/plans/polyglot-improvements/06-request-builder.md, Part A.
 */

function timestampProbeRequest(): InferenceRequest {
    return new InferenceRequest(messages: Messages::fromString('hello'), model: 'gpt-timestamps');
}

it('carries the same updatedAt instance across an InferenceRequest copy', function () {
    $request = timestampProbeRequest();

    $copy = $request->withModel('gpt-other');

    expect($copy->updatedAt)->toBe($request->updatedAt)
        ->and($copy->createdAt)->toBe($request->createdAt);
});

it('carries the same updatedAt instance across an InferenceResponse copy', function () {
    $response = new InferenceResponse(content: 'hi', finishReason: 'stop');

    $copy = $response->withContent('hi there');

    expect($copy->updatedAt)->toBe($response->updatedAt)
        ->and($copy->createdAt)->toBe($response->createdAt);
});

it('carries the same updatedAt instance across an InferenceAttempt copy', function () {
    $attempt = new InferenceAttempt();

    $copy = $attempt->withResponse(new InferenceResponse(content: 'hi', finishReason: 'stop'));

    expect($copy->updatedAt)->toBe($attempt->updatedAt)
        ->and($copy->createdAt)->toBe($attempt->createdAt);
});

it('carries the same updatedAt instance across an InferenceExecution copy', function () {
    $execution = InferenceExecution::fromRequest(timestampProbeRequest());

    $copy = $execution->startAttempt();

    expect($copy->updatedAt)->toBe($execution->updatedAt)
        ->and($copy->createdAt)->toBe($execution->createdAt);
});

it('does not advance updatedAt over a chain of copies', function () {
    // The per-attempt path runs several withers in a row; none of them may read the clock.
    $execution = InferenceExecution::fromRequest(timestampProbeRequest());

    $copy = $execution
        ->startAttempt()
        ->withRequest(timestampProbeRequest())
        ->withSuccessfulAttempt(new InferenceResponse(content: 'hi', finishReason: 'stop'));

    expect($copy->updatedAt)->toBe($execution->updatedAt);
});

it('serialises updatedAt equal to createdAt for a freshly built object and its copies', function () {
    $attempt = (new InferenceAttempt())->with(isFinalized: true);

    $data = $attempt->toArray();

    expect($data['updatedAt'])->toBe($data['createdAt']);
});

it('preserves a deserialised updatedAt across a copy', function () {
    // Before this change the wither clobbered the restored value with the copy time, so a
    // load-modify-save round trip silently lost it.
    $restored = InferenceAttempt::fromArray([
        'createdAt' => '2020-01-01T00:00:00+00:00',
        'updatedAt' => '2021-06-06T12:00:00+00:00',
    ]);

    $copy = $restored->with(isFinalized: true);

    expect($copy->toArray())
        ->toMatchArray([
            'createdAt' => '2020-01-01T00:00:00+00:00',
            'updatedAt' => '2021-06-06T12:00:00+00:00',
        ]);
});
