<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Data\DecisionProviderRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('round-trips a portable mixed request without execution or credential state', function () {
    $request = new DecisionRequest(
        input: JsonContent::fromJson('{"elements":{"1":{"label":"Wyślij"}},"history":[]}'),
        questions: Questions::of(new Noul('safe', 'Is this safe?')),
        model: 'jev-latest',
        retryPolicy: new DecisionRetryPolicy(maxAttempts: 3),
    );
    $portable = $request->toArray();
    $roundTrip = DecisionRequest::fromArray($portable);
    $input = $roundTrip->input()->value();

    expect(array_keys($portable))->toBe(['input', 'questions', 'model'])
        ->and($portable)->not->toHaveKeys(['id', 'apiKey', 'credentials', 'retryPolicy', 'telemetryCorrelation'])
        ->and($request->retryPolicy()?->maxAttempts)->toBe(3)
        ->and($roundTrip->retryPolicy())->toBeNull()
        ->and($roundTrip->toArray())->toEqual($portable)
        ->and($roundTrip->id()->equals($request->id()))->toBeFalse()
        ->and($input)->toBeInstanceOf(stdClass::class)
        ->and($input->elements)->toBeInstanceOf(stdClass::class)
        ->and(get_object_vars($input->elements))->toHaveKey('1');
});

it('generates request identity and omits absent model', function () {
    $request = new DecisionRequest('state', Questions::of(new Noul('q')));

    expect($request->toArray())->not->toHaveKey('model')
        ->and($request->id()->toString())->toMatch('/^[0-9a-f-]{36}$/')
        ->and($request->input()->value())->toBe('state');
});

it('requires executable requests to contain a question', function () {
    expect(fn () => new DecisionRequest('state', Questions::empty()))
        ->toThrow(InvalidArgumentException::class, 'requires at least one question')
        ->and(fn () => DecisionRequest::fromArray([
            'input' => 'state',
            'questions' => [['id' => 'q', 'type' => 'noul']],
            'secret' => 'must-not-be-accepted',
        ]))->toThrow(InvalidArgumentException::class, 'Unknown Decision request fields: secret');
});

it('preserves absent usage separately from zero and exposes response metadata', function () {
    $usage = new DecisionUsage(inputTokens: null, outputTokens: 0);
    $response = new DecisionResponse(
        answers: Answers::of(new NoulAnswer('safe', 0.8)),
        model: 'jev-1.13.0',
        usage: $usage,
        responseData: HttpResponse::sync(200, ['x-request-id' => 'provider-42'], '{"safe":true}'),
        providerRequestId: new DecisionProviderRequestId('provider-42'),
    );
    expect($usage->inputTokens())->toBeNull()
        ->and($usage->outputTokens())->toBe(0)
        ->and($usage->totalTokens())->toBeNull()
        ->and(DecisionUsage::fromArray($usage->toArray())->toArray())->toBe(['input' => null, 'output' => 0])
        ->and($response->answers()->noul('safe')->probability())->toBe(0.8)
        ->and($response->model())->toBe('jev-1.13.0')
        ->and($response->responseData()->statusCode())->toBe(200)
        ->and($response->providerRequestId()->toString())->toBe('provider-42');
});

it('rejects invalid usage counts and blank response models', function () {
    expect(fn () => new DecisionUsage(-1, 0))
        ->toThrow(InvalidArgumentException::class, 'non-negative integer')
        ->and(fn () => DecisionUsage::fromArray(['input' => '12', 'output' => 3]))
        ->toThrow(InvalidArgumentException::class, 'non-negative integer')
        ->and(fn () => new DecisionResponse(Answers::of(), ''))
        ->toThrow(InvalidArgumentException::class, 'non-empty string');
});
