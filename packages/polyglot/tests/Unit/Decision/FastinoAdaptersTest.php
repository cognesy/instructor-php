<?php

declare(strict_types=1);

use Cognesy\Http\Data\HttpResponse;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Drivers\Fastino\FastinoRequestAdapter;
use Cognesy\Polyglot\Decision\Drivers\Fastino\FastinoResponseAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Exceptions\DecisionResponseException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

// Recorded from POST https://api.fastino.ai/v1/systemone on 2026-10-06; includes
// the top-level `token_usage` aggregate that the OpenAPI response schema omits.
const FASTINO_LIVE_RESPONSE = __DIR__.'/../../Fixtures/Decision/fastino-live-response.json';

it('renders the Fastino System One body with an X-API-Key header', function (): void {
    $http = fastinoRequestAdapter()->toHttpClientRequest(fastinoRequest());
    $body = $http->body()->toArray();

    expect($http->url())->toBe('https://api.fastino.ai/v1/systemone')
        ->and($http->method())->toBe('POST')
        ->and($http->headers())->toBe([
            'X-API-Key' => 'synthetic-test-key',
            'Content-Type' => 'application/json',
            'Accept' => 'application/json',
        ])
        ->and(array_keys($body))->toBe(['state', 'model', 'questions'])
        ->and($body['model'])->toBe('fastino/GLiDE')
        ->and(array_keys($body['questions']))->toBe(['refund', 'department', 'urgency'])
        ->and($body['questions']['urgency']['criteria'])->toBe(['low', 'medium', 'high']);
});

it('passes Choice descriptions through unchanged, including missing and structured ones', function (): void {
    $request = new DecisionRequest(
        input: 'state',
        questions: Questions::of(new Choice(
            id: 'team',
            options: ChoiceOptions::of(
                new ChoiceOption('billing'),
                new ChoiceOption('returns', JsonContent::object(['handles' => ['returns']])),
            ),
            instructions: 'Which team?',
        )),
    );
    $criteria = fastinoRequestAdapter()->toHttpClientRequest($request)->body()->toArray()['questions']['team']['criteria'];

    expect($criteria)->toBe(['billing' => null, 'returns' => ['handles' => ['returns']]]);
});

it('accepts up to 255 Score levels and rejects more before HTTP', function (int $levels, bool $accepted): void {
    $request = fastinoScoreRequest(array_map(static fn (int $level): string => "Level {$level}", range(1, $levels)));
    $render = fn () => fastinoRequestAdapter()->toHttpClientRequest($request);

    match ($accepted) {
        true => expect($render()->body()->toArray()['questions']['scale']['criteria'])->toHaveCount($levels),
        false => expect($render)->toThrow(InvalidArgumentException::class, 'between 2 and 255 levels'),
    };
})->with([[11, true], [255, true], [256, false]]);

it('requires text instructions on every question before HTTP', function (Noul|Choice|Score $question): void {
    $request = new DecisionRequest(input: 'state', questions: Questions::of($question));

    expect(fn () => fastinoRequestAdapter()->toHttpClientRequest($request))
        ->toThrow(DecisionInvalidRequestException::class, 'Fastino requires text instructions');
})->with([
    'missing Noul instructions' => [new Noul('urgent')],
    'missing Choice instructions' => [new Choice('team', ChoiceOptions::of(new ChoiceOption('a'), new ChoiceOption('b')))],
    'structured Score instructions' => [new Score('severity', ScoreLevels::of('low', 'high'), JsonContent::object(['ask' => 'severity']))],
]);

it('requires text Score levels because Fastino echoes the legend as strings', function (): void {
    $request = fastinoScoreRequest(['low', JsonContent::object(['level' => 'high'])]);

    expect(fn () => fastinoRequestAdapter()->toHttpClientRequest($request))
        ->toThrow(DecisionInvalidRequestException::class, 'Score levels must be text');
});

it('requires a Fastino API key', function (): void {
    $adapter = new FastinoRequestAdapter(new DecisionConfig(
        driver: 'fastino',
        apiUrl: 'https://api.fastino.ai/v1',
        endpoint: '/systemone',
        model: 'fastino/GLiDE',
    ));

    expect(fn () => $adapter->toHttpClientRequest(fastinoRequest()))
        ->toThrow(InvalidArgumentException::class, "field 'apiKey' is missing or empty");
});

it('decodes a recorded live Fastino response', function (): void {
    $response = fastinoDecode(fastinoLiveBody());
    $urgency = $response->answers()->score('urgency');

    expect($response->model())->toBe('glide')
        ->and($response->answers()->noul('refund')->probability())->toBe(0.9975273766630417)
        ->and($response->answers()->choice('department')->value())->toBe('returns')
        ->and($response->answers()->choice('department')->confidence())->toBe(0.9990598446803479)
        ->and($urgency->value())->toBe(0.271362383978579)
        ->and($urgency->confidence())->toBe(0.5439840354626297)
        ->and($urgency->legend()->toArray())->toBe(['low', 'medium', 'high'])
        ->and($response->usage()->toArray())->toBe(['input' => 618, 'output' => 105])
        ->and($response->providerRequestId()->toString())->toBe('req-1');
});

it('accepts either tied level as the native Score selection', function (int $selected): void {
    $body = fastinoLiveObject();
    $body->answers->urgency = fastinoScoreAnswer($selected, [0.5, 0.5, 0.0], 0.5);

    expect(fastinoDecode(json_encode($body, JSON_THROW_ON_ERROR))->answers()->score('urgency')->value())->toBe(0.5);
})->with([0, 1]);

it('scales Score validation to 255 levels', function (): void {
    $levels = array_map(static fn (int $level): string => "Level {$level}", range(1, 255));
    $probabilities = array_fill(0, 255, 0.0);
    $probabilities[200] = 1.0;
    $body = new stdClass;
    $body->model = 'glide';
    $body->answers = (object) ['scale' => fastinoScoreAnswer(200, $probabilities, 200.0, $levels)];
    $body->usage = (object) ['input_tokens' => 10, 'output_tokens' => 1];

    $answer = (new FastinoResponseAdapter)->fromHttpResponse(
        fastinoHttpResponse(json_encode($body, JSON_THROW_ON_ERROR)),
        fastinoScoreRequest($levels),
    )->answers()->score('scale');

    expect($answer->value())->toBe(200.0);
});

it('fails closed on inconsistent or malformed Fastino answers', function (callable $mutate, string $message): void {
    $body = fastinoLiveObject();
    $mutate($body);

    expect(fn () => fastinoDecode(json_encode($body, JSON_THROW_ON_ERROR)))
        ->toThrow(DecisionResponseException::class, $message);
})->with([
    'Noul confidence off the documented formula' => [
        static function (stdClass $body): void { $body->answers->refund->confidence = 0.99; },
        'Noul confidence is inconsistent',
    ],
    'Score index that is not the most probable level' => [
        static function (stdClass $body): void { $body->answers->urgency->score = 1; },
        'most probable level index',
    ],
    'expected level off the distribution' => [
        static function (stdClass $body): void { $body->answers->urgency->expected_level = 1.5; },
        'probability-weighted value',
    ],
    'unknown native answer field' => [
        static function (stdClass $body): void { $body->answers->refund->extra = true; },
        'fields must exactly match',
    ],
    'missing native answer field' => [
        static function (stdClass $body): void { unset($body->answers->urgency->expected_level); },
        'fields must exactly match',
    ],
    'Choice probabilities that do not sum to one' => [
        static function (stdClass $body): void { $body->answers->department->probabilities->billing = 0.5; },
        'must sum to 1',
    ],
    'missing answer' => [
        static function (stdClass $body): void { unset($body->answers->department); },
        'answer IDs',
    ],
    'missing usage counter' => [
        static function (stdClass $body): void { unset($body->usage->input_tokens); },
        "usage field 'input_tokens'",
    ],
    'null usage counter' => [
        static function (stdClass $body): void { $body->usage->output_tokens = null; },
        "usage field 'output_tokens'",
    ],
]);

it('rejects a non-JSON Fastino response such as an HTML gateway page', function (): void {
    $http = HttpResponse::sync(200, ['content-type' => 'text/html'], '<html>gateway</html>');

    expect(fn () => (new FastinoResponseAdapter)->fromHttpResponse($http, fastinoRequest()))
        ->toThrow(DecisionResponseException::class, 'content type must be application/json');
});

function fastinoRequestAdapter(): FastinoRequestAdapter
{
    return new FastinoRequestAdapter(new DecisionConfig(
        driver: 'fastino',
        apiUrl: 'https://api.fastino.ai/v1/',
        apiKey: 'synthetic-test-key',
        endpoint: '/systemone',
        model: 'fastino/GLiDE',
    ));
}

function fastinoRequest(): DecisionRequest
{
    return new DecisionRequest(
        input: 'Refund request: the receipt is attached, the purchase was 10 days ago, and refunds are allowed within 30 days.',
        questions: Questions::of(
            new Noul('refund', 'Does this request qualify for a refund?'),
            new Choice(
                id: 'department',
                options: ChoiceOptions::of(
                    new ChoiceOption('billing', 'Payment or charge disputes'),
                    new ChoiceOption('returns', 'Refund or return requests'),
                    new ChoiceOption('shipping', 'Delivery or shipping issues'),
                ),
                instructions: 'Which team should handle this request?',
            ),
            new Score(
                id: 'urgency',
                levels: ScoreLevels::of('low', 'medium', 'high'),
                instructions: 'How urgent is this request?',
            ),
        ),
    );
}

/** @param list<string|JsonContent> $levels */
function fastinoScoreRequest(array $levels): DecisionRequest
{
    return new DecisionRequest(
        input: 'state',
        questions: Questions::of(new Score('scale', ScoreLevels::of(...$levels), 'Rate it.')),
    );
}

/**
 * @param list<float> $probabilities
 * @param list<string> $legend
 */
function fastinoScoreAnswer(int $score, array $probabilities, float $expected, array $legend = ['low', 'medium', 'high']): stdClass
{
    return (object) [
        'type' => 'score',
        'score' => $score,
        'expected_level' => $expected,
        'confidence' => 0.0,
        'probabilities' => (object) array_combine(array_map('strval', array_keys($probabilities)), $probabilities),
        'legend' => (object) array_combine(array_map('strval', array_keys($legend)), $legend),
    ];
}

function fastinoDecode(string $body): Cognesy\Polyglot\Decision\Data\DecisionResponse
{
    return (new FastinoResponseAdapter)->fromHttpResponse(fastinoHttpResponse($body), fastinoRequest());
}

function fastinoLiveObject(): stdClass
{
    return json_decode(fastinoLiveBody(), flags: JSON_THROW_ON_ERROR);
}

function fastinoLiveBody(): string
{
    $json = file_get_contents(FASTINO_LIVE_RESPONSE);
    if (! is_string($json)) {
        throw new RuntimeException('Fastino live response fixture cannot be read.');
    }

    return $json;
}

function fastinoHttpResponse(string $body): HttpResponse
{
    return HttpResponse::sync(200, ['content-type' => 'application/json', 'x-request-id' => 'req-1'], $body);
}
