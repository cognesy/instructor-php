<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionRequestId;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Drivers\ClassifierDev\ClassifierDevRequestAdapter;
use Cognesy\Polyglot\Decision\Exceptions\DecisionInvalidRequestException;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('renders one reversible fast-tier dimensions request with canonical structured content', function (): void {
    $request = new DecisionRequest(
        input: JsonContent::object(['z' => 2, 'a' => ['y' => 1, 'x' => 0]]),
        questions: Questions::of(
            new Choice('route?', ChoiceOptions::of(
                new ChoiceOption('007', JsonContent::object(['z' => 2, 'a' => 1])),
                new ChoiceOption('ops:/urgent'),
            ), JsonContent::object(['policy' => 'route exactly once'])),
            new Noul('safe!', criteria: new NoulCriteria(
                true: JsonContent::object(['kind' => 'safe']),
                false: 'unsafe',
            )),
            new Score('impact', ScoreLevels::of(
                JsonContent::object(['rank' => 0]),
                'high',
            )),
        ),
        id: new DecisionRequestId('00000000-0000-4000-8000-000000000007'),
    );
    $http = (new ClassifierDevRequestAdapter(classifierDevRequestConfig('test-key')))
        ->toHttpClientRequest($request);
    $body = $http->body()->toArray();

    expect($http->url())->toBe('https://classifier.dev/v1/classify')
        ->and($http->headers()['Authorization'])->toBe('Bearer test-key')
        ->and($http->headers()['Idempotency-Key'])->toBe('00000000-0000-4000-8000-000000000007')
        ->and($body['items'])->toBe(['{"a":{"x":0,"y":1},"z":2}'])
        ->and($body['tier'])->toBe('fast')
        ->and(array_keys($body['dimensions']))->toBe(['q0', 'q1', 'q2'])
        ->and($body['dimensions']['q0']['labels'])->toBe([
            '007: {"a":1,"z":2}',
            'ops:/urgent',
        ])
        ->and($body['dimensions']['q1']['labels'])->toBe([
            'yes: {"kind":"safe"}',
            'no: unsafe',
        ])
        ->and($body['dimensions']['q2']['labels'])->toBe([
            'level 0: {"rank":0}',
            'level 1: high',
        ]);
});

it('preserves text state and omits authorization for keyless classification', function (): void {
    $request = new DecisionRequest('text state', Questions::of(new Noul('safe')));
    $http = (new ClassifierDevRequestAdapter(classifierDevRequestConfig()))->toHttpClientRequest($request);

    expect($http->headers())->not->toHaveKey('Authorization')
        ->and($http->body()->toArray()['items'])->toBe(['text state']);
});

it('rejects smart tier locally', function (): void {
    $request = new DecisionRequest('state', Questions::of(new Noul('safe')), model: 'smart');

    expect(fn () => (new ClassifierDevRequestAdapter(classifierDevRequestConfig()))
        ->toHttpClientRequest($request))
        ->toThrow(DecisionInvalidRequestException::class, "only the 'fast' tier");
});

it('rejects rendered label collisions before HTTP', function (): void {
    $request = new DecisionRequest('state', Questions::of(new Choice(
        'route',
        ChoiceOptions::of(
            new ChoiceOption('a: b'),
            new ChoiceOption('a', 'b'),
        ),
    )));

    expect(fn () => (new ClassifierDevRequestAdapter(classifierDevRequestConfig()))
        ->toHttpClientRequest($request))
        ->toThrow(DecisionInvalidRequestException::class, 'labels must be unique');
});

it('rejects classifier.dev cardinality and length limits before HTTP', function (DecisionRequest $request): void {
    expect(fn () => (new ClassifierDevRequestAdapter(classifierDevRequestConfig()))
        ->toHttpClientRequest($request))
        ->toThrow(DecisionInvalidRequestException::class);
})->with([
    'twenty-one dimensions' => fn (): DecisionRequest => new DecisionRequest(
        'state',
        Questions::of(...array_map(
            static fn (int $index): Noul => new Noul("q{$index}"),
            range(0, 20),
        )),
    ),
    'one choice option' => fn (): DecisionRequest => new DecisionRequest(
        'state',
        Questions::of(new Choice('route', ChoiceOptions::of(new ChoiceOption('only')))),
    ),
    'one hundred and one labels' => fn (): DecisionRequest => new DecisionRequest(
        'state',
        Questions::of(new Choice('route', ChoiceOptions::of(...array_map(
            static fn (int $index): ChoiceOption => new ChoiceOption("option-{$index}"),
            range(0, 100),
        )))),
    ),
    'long input' => fn (): DecisionRequest => new DecisionRequest(
        str_repeat('x', 32_001),
        Questions::of(new Noul('safe')),
    ),
    'long label' => fn (): DecisionRequest => new DecisionRequest(
        'state',
        Questions::of(new Choice('route', ChoiceOptions::of(
            new ChoiceOption(str_repeat('x', 201)),
            new ChoiceOption('other'),
        ))),
    ),
    'long instructions' => fn (): DecisionRequest => new DecisionRequest(
        'state',
        Questions::of(new Noul('safe', str_repeat('x', 4_001))),
    ),
    'combined dimension definitions' => fn (): DecisionRequest => new DecisionRequest(
        'state',
        Questions::of(...array_map(
            static fn (int $index): Noul => new Noul("q{$index}", str_repeat('x', 3_300)),
            range(0, 4),
        )),
    ),
]);

function classifierDevRequestConfig(string $apiKey = ''): DecisionConfig {
    return new DecisionConfig(
        driver: 'classifier-dev',
        apiUrl: 'https://classifier.dev',
        apiKey: $apiKey,
        endpoint: '/v1/classify',
        model: 'fast',
    );
}
