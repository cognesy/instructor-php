<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Drivers\TypeSafe\TypesafeResponseAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

const TYPESAFE_GOLDEN_RESPONSES = __DIR__.'/../../Fixtures/Decision/typesafe-golden-responses.json';

it('decodes and correlates a mixed TypeSafe response', function () {
    $httpResponse = decisionTypesafeHttpResponse('mixed', [
        'Content-Type' => ['application/json; charset=utf-8'],
        'X-TypeSafe-Request-Id' => ['provider-request-42'],
    ]);
    $response = (new TypesafeResponseAdapter)->fromHttpResponse(
        $httpResponse,
        decisionTypesafeRequest('mixed'),
    );

    expect($response->model())->toBe('jev-test-2026-09-17')
        ->and($response->answers()->count())->toBe(3)
        ->and($response->answers()->noul('urgent')->probability())->toBe(0.93)
        ->and($response->answers()->choice('route')->value())->toBe('technical')
        ->and($response->answers()->choice('route')->probabilities()->probability('technical'))->toBe(0.8)
        ->and($response->answers()->score('impact')->value())->toBe(1.2)
        ->and($response->answers()->score('impact')->legend()->at(1)->value())->toEqual((object) [
            'label' => 'Degraded',
            'workaround' => true,
        ])
        ->and($response->usage()->inputTokens())->toBe(321)
        ->and($response->usage()->outputTokens())->toBe(47)
        ->and($response->providerRequestId()->toString())->toBe('provider-request-42')
        ->and($response->responseData())->toBe($httpResponse);
});

it('accepts tied Choice winners and ordinary provider rounding', function () {
    $adapter = new TypesafeResponseAdapter;
    $tie = $adapter->fromHttpResponse(
        decisionTypesafeHttpResponse('choice_tie'),
        decisionTypesafeRequest('choice_tie'),
    );
    $rounded = $adapter->fromHttpResponse(
        decisionTypesafeHttpResponse('rounded_score'),
        decisionTypesafeRequest('rounded_score'),
    );

    expect($tie->answers()->choice('route')->value())->toBe('alpha')
        ->and($tie->answers()->choice('route')->probabilities()->highest())->toBe(0.5)
        ->and($rounded->answers()->score('impact')->value())->toBe(0.6667)
        ->and($rounded->answers()->score('impact')->probabilities()->expectedValue())->toBe(0.6666);
});

it('preserves numeric-looking question and Choice option IDs', function () {
    $response = (new TypesafeResponseAdapter)->fromHttpResponse(
        decisionTypesafeHttpResponse('numeric_ids'),
        decisionTypesafeRequest('numeric_ids'),
    );

    expect($response->answers()->choice('0')->value())->toBe('1')
        ->and($response->answers()->choice('0')->probabilities()->ids())->toBe(['0', '1']);
});

it('matches structured Score legend objects independently of property order', function () {
    $body = decisionTypesafeFixture('mixed');
    $body->answers->impact->legend->{'1'} = (object) [
        'workaround' => true,
        'label' => 'Degraded',
    ];

    $response = (new TypesafeResponseAdapter)->fromHttpResponse(
        decisionTypesafeResponseFromBody($body),
        decisionTypesafeRequest('mixed'),
    );

    expect($response->answers()->score('impact')->value())->toBe(1.2);
});

it('rejects request-correlated answer violations', function (Closure $mutate, string $message) {
    $body = decisionTypesafeFixture('mixed');
    $mutate($body);

    expect(fn () => (new TypesafeResponseAdapter)->fromHttpResponse(
        decisionTypesafeResponseFromBody($body),
        decisionTypesafeRequest('mixed'),
    ))->toThrow(DecisionResponseException::class, $message);
})->with([
    'missing answer' => [
        static function (stdClass $body): void {
            unset($body->answers->urgent);
        },
        'answer IDs must exactly match',
    ],
    'extra answer' => [
        static function (stdClass $body): void {
            $body->answers->extra = (object) ['type' => 'noul', 'noul' => 0.5];
        },
        'answer IDs must exactly match',
    ],
    'wrong primitive' => [
        static function (stdClass $body): void {
            $body->answers->urgent->type = 'choice';
        },
        'type does not match',
    ],
    'numeric string' => [
        static function (stdClass $body): void {
            $body->answers->urgent->noul = '0.93';
        },
        'finite number',
    ],
    'boolean number' => [
        static function (stdClass $body): void {
            $body->answers->route->confidence = true;
        },
        'finite number',
    ],
    'unknown choice' => [
        static function (stdClass $body): void {
            $body->answers->route->choice = 'sales';
        },
        'unknown option',
    ],
    'missing choice probability' => [
        static function (stdClass $body): void {
            unset($body->answers->route->probabilities->billing);
        },
        'probability keys must exactly match',
    ],
    'choice is not a winner' => [
        static function (stdClass $body): void {
            $body->answers->route->choice = 'billing';
        },
        'must have the highest probability',
    ],
    'bad probability sum' => [
        static function (stdClass $body): void {
            $body->answers->route->probabilities->technical = 0.7;
        },
        'probabilities must sum to 1',
    ],
    'score weighted value mismatch' => [
        static function (stdClass $body): void {
            $body->answers->impact->score = 1.4;
        },
        'probability-weighted value',
    ],
    'score legend mismatch' => [
        static function (stdClass $body): void {
            $body->answers->impact->legend->{'2'} = 'Different';
        },
        'legend does not match',
    ],
    'usage numeric string' => [
        static function (stdClass $body): void {
            $body->usage->input_tokens = '321';
        },
        'non-negative integer',
    ],
    'missing usage' => [
        static function (stdClass $body): void {
            unset($body->usage);
        },
        'response usage must be an object',
    ],
    'blank model' => [
        static function (stdClass $body): void {
            $body->model = ' ';
        },
        'model must be a non-empty string',
    ],
    'unknown answer field' => [
        static function (stdClass $body): void {
            $body->answers->urgent->confidence = 0.9;
        },
        'fields must exactly match',
    ],
]);

it('rejects malformed, nonfinite, and non-JSON responses without echoing bodies', function () {
    $request = decisionTypesafeRequest('mixed');
    $adapter = new TypesafeResponseAdapter;
    $nonfinite = str_replace('"noul":0.93', '"noul":1e400', decisionTypesafeJson('mixed'));

    expect(fn () => $adapter->fromHttpResponse(
        HttpResponse::sync(200, ['content-type' => 'application/json'], '{provider-secret'),
        $request,
    ))->toThrow(DecisionResponseException::class, 'malformed JSON')
        ->and(fn () => $adapter->fromHttpResponse(
            HttpResponse::sync(200, ['content-type' => 'application/json'], $nonfinite),
            $request,
        ))->toThrow(DecisionResponseException::class, 'finite number')
        ->and(fn () => $adapter->fromHttpResponse(
            HttpResponse::sync(200, ['content-type' => 'text/plain'], 'provider-secret'),
            $request,
        ))->toThrow(DecisionResponseException::class, 'content type');

    try {
        $adapter->fromHttpResponse(
            HttpResponse::sync(200, ['content-type' => 'application/json'], '{provider-secret'),
            $request,
        );
    } catch (DecisionResponseException $exception) {
        expect($exception->getMessage())->not->toContain('provider-secret');
    }
});

function decisionTypesafeRequest(string $case): DecisionRequest
{
    return match ($case) {
        'mixed' => new DecisionRequest(
            input: 'The checkout is unavailable and orders are blocked.',
            questions: Questions::of(
                new Noul('urgent'),
                new Choice('route', ChoiceOptions::of(
                    new ChoiceOption('billing', 'Billing'),
                    new ChoiceOption('technical', 'Technical support'),
                )),
                new Score('impact', ScoreLevels::of(
                    'No impact',
                    JsonContent::object(['label' => 'Degraded', 'workaround' => true]),
                    JsonContent::list(['Blocked', (object) ['workaround' => false]]),
                )),
            ),
        ),
        'choice_tie' => new DecisionRequest(
            input: 'Either route is equally suitable.',
            questions: Questions::of(new Choice('route', ChoiceOptions::of(
                new ChoiceOption('alpha'),
                new ChoiceOption('beta'),
            ))),
        ),
        'rounded_score' => new DecisionRequest(
            input: 'Mostly high impact.',
            questions: Questions::of(new Score('impact', ScoreLevels::of('Low', 'High'))),
        ),
        'numeric_ids' => new DecisionRequest(
            input: 'Pick the second numeric option.',
            questions: Questions::of(new Choice('0', ChoiceOptions::of(
                new ChoiceOption('0'),
                new ChoiceOption('1'),
            ))),
        ),
        default => throw new InvalidArgumentException("Unknown response fixture '{$case}'."),
    };
}

function decisionTypesafeHttpResponse(string $case, array $headers = []): HttpResponse
{
    return HttpResponse::sync(
        statusCode: 200,
        headers: ['content-type' => 'application/json', ...$headers],
        body: decisionTypesafeJson($case),
    );
}

function decisionTypesafeResponseFromBody(stdClass $body): HttpResponse
{
    $json = json_encode($body, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    return HttpResponse::sync(200, ['content-type' => 'application/json'], $json);
}

function decisionTypesafeFixture(string $case): stdClass
{
    $body = json_decode(decisionTypesafeJson($case), associative: false, flags: JSON_THROW_ON_ERROR);
    if (! $body instanceof stdClass) {
        throw new RuntimeException('TypeSafe golden response case must be an object.');
    }

    return $body;
}

function decisionTypesafeJson(string $case): string
{
    $json = file_get_contents(TYPESAFE_GOLDEN_RESPONSES);
    if (! is_string($json)) {
        throw new RuntimeException('TypeSafe golden response fixture cannot be read.');
    }
    $fixture = json_decode($json, associative: false, flags: JSON_THROW_ON_ERROR);
    if (! $fixture instanceof stdClass || ! property_exists($fixture, $case)) {
        throw new RuntimeException("TypeSafe golden response case '{$case}' does not exist.");
    }
    $body = json_encode($fixture->{$case}, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

    return $body;
}
