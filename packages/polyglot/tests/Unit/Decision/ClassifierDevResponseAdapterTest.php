<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Drivers\ClassifierDev\ClassifierDevResponseAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('decodes mixed dimensions back to original IDs, polarity, and score legend', function (): void {
    $request = classifierDevMixedRequest();
    $http = classifierDevResponse([
        'q0' => classifierDevDimension(
            '007: {"a":1,"z":2}',
            0.73,
            ['007: {"a":1,"z":2}' => 0.7, 'ops:/urgent' => 0.3],
        ),
        'q1' => classifierDevDimension(
            'yes: {"kind":"safe"}',
            0.84,
            ['yes: {"kind":"safe"}' => 0.8, 'no: unsafe' => 0.2],
        ),
        'q2' => classifierDevDimension(
            'level 1: high',
            0.69,
            ['level 0: {"rank":0}' => 0.25, 'level 1: high' => 0.75],
        ),
    ], ['idempotency-key' => 'provider-request-7']);

    $response = (new ClassifierDevResponseAdapter())->fromHttpResponse($http, $request);

    expect($response->model())->toBe('jev-test')
        ->and($response->providerRequestId()->toString())->toBe('provider-request-7')
        ->and($response->usage()->inputTokens())->toBeNull()
        ->and($response->usage()->outputTokens())->toBeNull()
        ->and($response->answers()->choice('route?')->value())->toBe('007')
        ->and($response->answers()->choice('route?')->confidence())->toBe(0.73)
        ->and($response->answers()->choice('route?')->probabilities()->ids())->toBe(['007', 'ops:/urgent'])
        ->and($response->answers()->noul('safe!')->probability())->toBe(0.8)
        ->and($response->answers()->noul('safe!')->confidence())->toBe(0.8)
        ->and($response->answers()->score('impact')->value())->toBe(0.75)
        ->and($response->answers()->score('impact')->confidence())->toBe(0.69)
        ->and($response->answers()->score('impact')->legend()->toArray())->toEqual([
            (object) ['rank' => 0],
            'high',
        ]);
});

it('fails closed when classifier.dev omits scores', function (): void {
    $request = new DecisionRequest('state', Questions::of(new Noul('safe')));
    $http = classifierDevResponse([
        'q0' => [
            'label' => 'yes',
            'confidence' => null,
            'scores' => null,
            'model' => 'fallback-model',
            'ms' => 9,
        ],
    ]);

    expect(fn () => (new ClassifierDevResponseAdapter())->fromHttpResponse($http, $request))
        ->toThrow(DecisionResponseException::class);
});

it('rejects incomplete, unknown, non-normalized, and inconsistent distributions', function (array $dimension): void {
    $request = new DecisionRequest('state', Questions::of(new Choice(
        'route',
        ChoiceOptions::of(new ChoiceOption('a'), new ChoiceOption('b')),
    )));
    $http = classifierDevResponse(['q0' => $dimension]);

    expect(fn () => (new ClassifierDevResponseAdapter())->fromHttpResponse($http, $request))
        ->toThrow(DecisionResponseException::class);
})->with([
    'missing label' => [[
        'label' => 'a', 'confidence' => 0.8, 'scores' => ['a' => 1.0], 'model' => 'jev', 'ms' => 2,
    ]],
    'unknown label' => [[
        'label' => 'a', 'confidence' => 0.8, 'scores' => ['a' => 0.8, 'b' => 0.1, 'c' => 0.1], 'model' => 'jev', 'ms' => 2,
    ]],
    'non-normalized' => [[
        'label' => 'a', 'confidence' => 0.8, 'scores' => ['a' => 0.8, 'b' => 0.8], 'model' => 'jev', 'ms' => 2,
    ]],
    'selected label not maximum' => [[
        'label' => 'a', 'confidence' => 0.8, 'scores' => ['a' => 0.2, 'b' => 0.8], 'model' => 'jev', 'ms' => 2,
    ]],
]);

it('rejects malformed successful responses without exposing bodies', function (): void {
    $request = new DecisionRequest('state', Questions::of(new Noul('safe')));
    $http = HttpResponse::sync(200, ['content-type' => 'application/json'], '{private-state');

    try {
        (new ClassifierDevResponseAdapter())->fromHttpResponse($http, $request);
        test()->fail('Expected malformed classifier.dev success response to fail.');
    } catch (DecisionResponseException $exception) {
        expect($exception->getMessage())->toContain('malformed JSON')
            ->not->toContain('private-state');
    }
});

function classifierDevMixedRequest(): DecisionRequest {
    return new DecisionRequest(
        input: 'state',
        questions: Questions::of(
            new Choice('route?', ChoiceOptions::of(
                new ChoiceOption('007', JsonContent::object(['z' => 2, 'a' => 1])),
                new ChoiceOption('ops:/urgent'),
            )),
            new Noul('safe!', criteria: new NoulCriteria(
                true: JsonContent::object(['kind' => 'safe']),
                false: 'unsafe',
            )),
            new Score('impact', ScoreLevels::of(
                JsonContent::object(['rank' => 0]),
                'high',
            )),
        ),
    );
}

/** @param array<string, float> $scores */
function classifierDevDimension(string $label, float $confidence, array $scores): array {
    return [
        'label' => $label,
        'confidence' => $confidence,
        'scores' => $scores,
        'model' => 'jev-test',
        'ms' => 7,
    ];
}

/** @param array<string, mixed> $dimensions @param array<string, string> $headers */
function classifierDevResponse(array $dimensions, array $headers = []): HttpResponse {
    return HttpResponse::sync(
        200,
        ['content-type' => 'application/json', ...$headers],
        json_encode([
            'tier' => 'fast',
            'model' => 'jev-test',
            'modelsUsed' => ['jev-test'],
            'results' => [['dimensions' => $dimensions]],
            'usage' => ['items' => 1, 'dimensions' => count($dimensions), 'classifications' => count($dimensions), 'ms' => 7],
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    );
}
