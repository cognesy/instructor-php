<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Drivers\Laya\LayaResponseAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

const LAYA_GOLDEN_RESPONSES = __DIR__ . '/../../Fixtures/Decision/laya-responses.json';

it('normalizes captured Laya backend responses with typed action signals', function (string $backend): void {
    $fixture = layaGoldenResponses()->{$backend};
    $http = HttpResponse::sync(
        200,
        [
            'content-type' => 'application/json',
            'x-request-id' => "{$backend}-request-7",
            'x-laya-backend' => $backend,
            'x-laya-revision' => "{$backend}-checkpoint",
        ],
        json_encode($fixture, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    );

    $response = (new LayaResponseAdapter())->fromHttpResponse($http, layaResponseRequest());

    expect($response->model())->toBe($fixture->model)
        ->and($response->providerRequestId()->toString())->toBe("{$backend}-request-7")
        ->and($response->answers()->choice('route')->value())->toBeIn(['billing', 'technical'])
        ->and($response->answers()->choice('route')->signals()->modelActionProbability())
        ->toBe($fixture->answers->route->action->act_probability)
        ->and($response->answers()->noul('safe')->signals()->modelActionProbability())
        ->toBe($fixture->answers->safe->action->act_probability)
        ->and($response->answers()->score('impact')->signals()->modelActionProbability())
        ->toBe($fixture->answers->impact->action->act_probability)
        ->and($response->answers()->score('impact')->legend()->toArray())
        ->toEqual(['low', (object) ['label' => 'high']])
        ->and($response->usage()->outputTokens())->toBe(0)
        ->and($response->responseData()->headers()['x-laya-backend'])->toBe($backend);

    $roundTrip = Answers::fromArray($response->answers()->toArray());
    expect($roundTrip->choice('route')->signals()->modelActionProbability())
        ->toBe($fixture->answers->route->action->act_probability);
})->with(['mlx', 'torch']);

it('rejects unknown Laya answer and action extensions', function (string $location): void {
    $body = json_decode(json_encode(layaGoldenResponses()->mlx, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
    if ($location === 'answer') {
        $body->answers->route->debug = 'private-path';
    } else {
        $body->answers->route->action->policy_override = true;
    }
    $http = layaHttpResponse($body);

    expect(fn () => (new LayaResponseAdapter())->fromHttpResponse($http, layaResponseRequest()))
        ->toThrow(DecisionResponseException::class);
})->with(['answer', 'action']);

it('rejects inconsistent redundant Noul confidence', function (): void {
    $body = json_decode(json_encode(layaGoldenResponses()->mlx, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
    $body->answers->safe->confidence = 0.2;

    expect(fn () => (new LayaResponseAdapter())->fromHttpResponse(
        layaHttpResponse($body),
        layaResponseRequest(),
    ))->toThrow(DecisionResponseException::class, 'confidence is inconsistent');
});

it('keeps canonical distribution invariants after removing Laya extensions', function (): void {
    $body = json_decode(json_encode(layaGoldenResponses()->mlx, JSON_THROW_ON_ERROR), false, flags: JSON_THROW_ON_ERROR);
    $body->answers->route->choice = 'technical';

    expect(fn () => (new LayaResponseAdapter())->fromHttpResponse(
        layaHttpResponse($body),
        layaResponseRequest(),
    ))->toThrow(DecisionResponseException::class, 'highest probability');
});

it('rejects malformed successful Laya responses without exposing bodies', function (): void {
    $http = HttpResponse::sync(200, ['content-type' => 'application/json'], '{private-state');

    try {
        (new LayaResponseAdapter())->fromHttpResponse($http, layaResponseRequest());
        test()->fail('Expected malformed Laya success response to fail.');
    } catch (DecisionResponseException $exception) {
        expect($exception->getMessage())->toContain('malformed JSON')
            ->not->toContain('private-state');
    }
});

function layaResponseRequest(): DecisionRequest {
    return new DecisionRequest('state', Questions::of(
        new Choice('route', ChoiceOptions::of(
            new ChoiceOption('billing'),
            new ChoiceOption('technical'),
        ), 'Route?'),
        new Noul('safe', 'Safe?'),
        new Score('impact', ScoreLevels::of('low', JsonContent::object(['label' => 'high'])), 'Impact?'),
    ));
}

function layaGoldenResponses(): stdClass {
    $decoded = json_decode(
        file_get_contents(LAYA_GOLDEN_RESPONSES),
        associative: false,
        flags: JSON_THROW_ON_ERROR,
    );
    if (!$decoded instanceof stdClass) {
        throw new RuntimeException('Laya golden responses fixture must be an object.');
    }

    return $decoded;
}

function layaHttpResponse(stdClass $body): HttpResponse {
    return HttpResponse::sync(
        200,
        ['content-type' => 'application/json'],
        json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    );
}
