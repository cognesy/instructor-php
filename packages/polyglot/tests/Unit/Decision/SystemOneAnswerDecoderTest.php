<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Answers\AnswerSignals;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Data\DecisionProviderRequestId;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionUsage;
use Cognesy\Polyglot\Decision\Drivers\SystemOne\SystemOneAnswerDecoder;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('builds strict domain answers from normalized System One data and typed signals', function (): void {
    $request = new DecisionRequest('state', Questions::of(new Noul('safe')), model: 'model');
    $http = HttpResponse::sync(200, ['content-type' => 'application/json'], '{}');
    $response = (new SystemOneAnswerDecoder(
        provider: 'Compatible provider',
        probabilitySumTolerance: 0.001,
        scoreValueTolerance: 0.001,
    ))->decode(
        wireAnswers: (object) ['safe' => (object) ['type' => 'noul', 'noul' => 0.7]],
        request: $request,
        model: 'model-v1',
        usage: new DecisionUsage(12, 3),
        responseData: $http,
        providerRequestId: new DecisionProviderRequestId('request-1'),
        signals: ['safe' => new AnswerSignals(0.82)],
    );

    expect($response->answers()->noul('safe')->probability())->toBe(0.7)
        ->and($response->answers()->noul('safe')->signals()->modelActionProbability())->toBe(0.82)
        ->and($response->model())->toBe('model-v1')
        ->and($response->usage()->totalTokens())->toBe(15)
        ->and($response->providerRequestId()->toString())->toBe('request-1')
        ->and($response->responseData())->toBe($http);
});

it('uses the provider label in safe strict-decoding errors', function (): void {
    $request = new DecisionRequest('state', Questions::of(new Noul('safe')), model: 'model');
    $decoder = new SystemOneAnswerDecoder(
        provider: 'Local provider',
        probabilitySumTolerance: 0.001,
        scoreValueTolerance: 0.001,
    );

    expect(fn () => $decoder->decode(
        wireAnswers: (object) [
            'safe' => (object) ['type' => 'noul', 'noul' => 0.7, 'rawSecret' => 'not echoed'],
        ],
        request: $request,
        model: 'model',
        usage: new DecisionUsage,
        responseData: HttpResponse::sync(200, [], '{}'),
        providerRequestId: new DecisionProviderRequestId,
    ))->toThrow(DecisionResponseException::class, 'Local provider Noul answer')
        ->and(fn () => $decoder->decode(
            wireAnswers: (object) [
                'safe' => (object) ['type' => 'noul', 'noul' => 0.7, 'rawSecret' => 'not echoed'],
            ],
            request: $request,
            model: 'model',
            usage: new DecisionUsage,
            responseData: HttpResponse::sync(200, [], '{}'),
            providerRequestId: new DecisionProviderRequestId,
        ))->toThrow(DecisionResponseException::class, 'fields must exactly match');
});
