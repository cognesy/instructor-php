<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\Jeff\JeffResponseAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('decodes consistent Jeff answers and preserves request and timing headers', function (): void {
    $request = new DecisionRequest('state', Questions::of(
        new Noul('safe'),
        new Choice('route', ChoiceOptions::of(new ChoiceOption('a'), new ChoiceOption('b'))),
        new Score('impact', ScoreLevels::of('low', 'high')),
    ));
    $http = jeffResponse([
        'safe' => ['type' => 'noul', 'noul' => 0.8],
        'route' => [
            'type' => 'choice',
            'choice' => 'a',
            'confidence' => 0.6,
            'probabilities' => ['a' => 0.6, 'b' => 0.4],
        ],
        'impact' => [
            'type' => 'score',
            'score' => 0.8,
            'confidence' => 0.6,
            'probabilities' => (object) [0 => 0.2, 1 => 0.8],
            'legend' => (object) [0 => 'low', 1 => 'high'],
        ],
    ], [
        'x-typesafe-request-id' => 'jeff-request-7',
        'x-jeff-server-ms' => '12.4',
        'x-jeff-batcher-ms' => '2.1',
    ]);

    $response = (new JeffResponseAdapter)->fromHttpResponse($http, $request);

    expect($response->answers()->noul('safe')->probability())->toBe(0.8)
        ->and($response->answers()->choice('route')->value())->toBe('a')
        ->and($response->answers()->score('impact')->value())->toBe(0.8)
        ->and($response->providerRequestId()->toString())->toBe('jeff-request-7')
        ->and($response->responseData()->headers()['x-jeff-server-ms'])->toBe('12.4')
        ->and($response->responseData()->headers()['x-jeff-batcher-ms'])->toBe('2.1');
});

it('fails closed for a Score inconsistent with the displayed distribution', function (): void {
    $request = new DecisionRequest(
        'state',
        Questions::of(new Score('impact', ScoreLevels::of('low', 'high'))),
    );
    $http = jeffResponse([
        'impact' => [
            'type' => 'score',
            'score' => 0.8,
            'confidence' => 0.6,
            'probabilities' => (object) [0 => 0.75, 1 => 0.25],
            'legend' => (object) [0 => 'low', 1 => 'high'],
        ],
    ]);

    expect(fn () => (new JeffResponseAdapter)->fromHttpResponse($http, $request))
        ->toThrow(DecisionResponseException::class, 'probability-weighted value');
});

it('accepts independently rounded Jeff Choice distributions', function (int $count): void {
    $ids = array_map(static fn (int $index): string => "option-{$index}", range(0, $count - 1));
    $options = array_map(static fn (string $id): ChoiceOption => new ChoiceOption($id), $ids);
    $probability = round(1 / $count, 4);
    $wireProbabilities = array_fill_keys($ids, $probability);
    $request = new DecisionRequest(
        'state',
        Questions::of(new Choice('route', ChoiceOptions::of(...$options))),
    );
    $http = jeffResponse([
        'route' => [
            'type' => 'choice',
            'choice' => $ids[0],
            'confidence' => 0.0,
            'probabilities' => $wireProbabilities,
        ],
    ]);

    $answer = (new JeffResponseAdapter)->fromHttpResponse($http, $request)->answers()->choice('route');

    expect($answer->probabilities()->ids())->toBe($ids)
        ->and(array_sum(array_column($answer->probabilities()->all(), 'probability')))
        ->toBeCloseTo($probability * $count);
})->with([2, 10, 64]);

it('rejects malformed successful Jeff responses without exposing bodies', function (): void {
    $request = new DecisionRequest('state', Questions::of(new Noul('safe')));
    $http = HttpResponse::sync(
        200,
        ['content-type' => 'application/json'],
        '{provider-secret',
    );

    try {
        (new JeffResponseAdapter)->fromHttpResponse($http, $request);
        test()->fail('Expected malformed Jeff success response to fail.');
    } catch (DecisionResponseException $exception) {
        expect($exception->getMessage())->toContain('malformed JSON')
            ->not->toContain('provider-secret');
    }
});

/** @param array<string, mixed> $answers @param array<string, string> $headers */
function jeffResponse(array $answers, array $headers = []): HttpResponse
{
    return HttpResponse::sync(
        200,
        ['content-type' => 'application/json', ...$headers],
        json_encode([
            'model' => 'gliformer-large-v1',
            'answers' => $answers,
            'usage' => ['input_tokens' => 12, 'output_tokens' => 3],
        ], JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    );
}
