<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Drivers\Respan\RespanResponseAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('decodes RESPAN ternary answers by ID with optional input-only usage', function (): void {
    $request = new DecisionRequest(
        input: 'unused by response adapter',
        questions: Questions::of(
            new Noul('frustrated', 'The user is frustrated.'),
            new Noul('apology', 'The assistant apologizes.'),
        ),
        model: 'span-01-free',
    );
    $http = respanResponse([
        [
            'id' => 'apology',
            'p_present' => 0.94,
            'p_absent' => 0.04,
            'p_not_observable' => 0.02,
        ],
        [
            'id' => 'frustrated',
            'p_present' => 0.45,
            'p_absent' => 0.53,
            'p_not_observable' => 0.02,
        ],
    ], ['input_tokens' => 39]);

    $response = (new RespanResponseAdapter)->fromHttpResponse($http, $request);
    $frustrated = $response->answers()->noul('frustrated');

    expect($frustrated->probability())->toBe(0.45)
        ->and($frustrated->confidence())->toBe(0.53)
        ->and($frustrated->probabilities()->negative())->toBe(0.53)
        ->and($frustrated->probabilities()->unknown())->toBe(0.02)
        ->and($response->answers()->noul('apology')->probability())->toBe(0.94)
        ->and($response->usage()->inputTokens())->toBe(39)
        ->and($response->usage()->outputTokens())->toBeNull()
        ->and($response->providerRequestId()->toString())->toBe('respan-log-7');
});

it('preserves absent usage as null', function (): void {
    $request = new DecisionRequest(
        input: 'unused',
        questions: Questions::of(new Noul('safe', 'The response is safe.')),
        model: 'span-01-free',
    );
    $http = respanResponse([[
        'id' => 'safe',
        'p_present' => 0.7,
        'p_absent' => 0.2,
        'p_not_observable' => 0.1,
    ]], usage: null);

    $usage = (new RespanResponseAdapter)->fromHttpResponse($http, $request)->usage();

    expect($usage->inputTokens())->toBeNull()
        ->and($usage->outputTokens())->toBeNull();
});

it('rejects missing duplicate unexpected and invalid RESPAN results', function (
    array $results,
    string $message,
): void {
    $request = new DecisionRequest(
        input: 'unused',
        questions: Questions::of(new Noul('safe', 'The response is safe.')),
        model: 'span-01-free',
    );

    expect(fn () => (new RespanResponseAdapter)->fromHttpResponse(
        respanResponse($results),
        $request,
    ))->toThrow(DecisionResponseException::class, $message);
})->with([
    'missing' => [[], 'is missing'],
    'duplicate' => [[
        ['id' => 'safe', 'p_present' => 0.7, 'p_absent' => 0.2, 'p_not_observable' => 0.1],
        ['id' => 'safe', 'p_present' => 0.7, 'p_absent' => 0.2, 'p_not_observable' => 0.1],
    ], 'must be unique'],
    'unexpected' => [[
        ['id' => 'safe', 'p_present' => 0.7, 'p_absent' => 0.2, 'p_not_observable' => 0.1],
        ['id' => 'other', 'p_present' => 0.7, 'p_absent' => 0.2, 'p_not_observable' => 0.1],
    ], 'unexpected result IDs'],
    'invalid distribution' => [[
        ['id' => 'safe', 'p_present' => 0.2, 'p_absent' => 0.2, 'p_not_observable' => 0.2],
    ], 'sum to 1'],
]);

it('rejects RESPAN results encoded as an object', function (): void {
    $request = new DecisionRequest(
        input: 'unused',
        questions: Questions::of(new Noul('safe', 'The response is safe.')),
        model: 'span-01-free',
    );
    $http = HttpResponse::sync(
        200,
        ['content-type' => 'application/json'],
        '{"model":"span-01-free","results":{"safe":{"id":"safe"}}}',
    );

    expect(fn () => (new RespanResponseAdapter)->fromHttpResponse($http, $request))
        ->toThrow(DecisionResponseException::class, 'must be a list');
});

/** @param list<array<string, mixed>> $results @param array{input_tokens: int}|null $usage */
function respanResponse(array $results, ?array $usage = ['input_tokens' => 12]): HttpResponse
{
    $body = ['model' => 'span-01-free', 'results' => $results];
    if ($usage !== null) {
        $body['usage'] = $usage;
    }

    return HttpResponse::sync(
        200,
        ['content-type' => 'application/json', 'x-respan-log-id' => 'respan-log-7'],
        json_encode($body, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION),
    );
}
