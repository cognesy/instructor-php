<?php

declare(strict_types=1);

use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\Drivers\TypeSafe\TypesafeRequestAdapter;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

const TYPESAFE_GOLDEN_REQUESTS = __DIR__.'/../../Fixtures/Decision/typesafe-golden-requests.json';

it('renders exact TypeSafe wire requests from golden fixtures', function (string $case) {
    $expected = typesafeGoldenRequests()->{$case};
    $http = typesafeRequestAdapter()->toHttpClientRequest(typesafeGoldenDomainRequest($case));
    $actualBody = json_decode($http->body()->toString(), associative: false, flags: JSON_THROW_ON_ERROR);

    expect($http->url())->toBe($expected->url)
        ->and($http->method())->toBe($expected->method)
        ->and($http->headers())->toBe(get_object_vars($expected->headers))
        ->and($actualBody)->toEqual($expected->body);
})->with(['simple_noul', 'structured_mixed_numeric_ids', 'list_state_choice']);

it('preserves contiguous numeric-looking question and option IDs as JSON objects', function () {
    $http = typesafeRequestAdapter()->toHttpClientRequest(
        typesafeGoldenDomainRequest('structured_mixed_numeric_ids'),
    );
    $body = json_decode($http->body()->toString(), associative: false, flags: JSON_THROW_ON_ERROR);

    expect($body->questions)->toBeInstanceOf(stdClass::class)
        ->and(get_object_vars($body->questions))->toHaveKeys(['0', '1', 'safe'])
        ->and($body->questions->{'0'}->criteria)->toBeInstanceOf(stdClass::class)
        ->and(get_object_vars($body->questions->{'0'}->criteria))->toHaveKeys(['0', '1'])
        ->and($body->state->elements)->toBeInstanceOf(stdClass::class)
        ->and(get_object_vars($body->state->elements))->toHaveKeys(['0', '1']);
});

it('uses the config model unless the request supplies an override', function () {
    $adapter = typesafeRequestAdapter();
    $default = $adapter->toHttpClientRequest(typesafeGoldenDomainRequest('list_state_choice'));
    $override = $adapter->toHttpClientRequest(typesafeGoldenDomainRequest('simple_noul'));

    expect($default->body()->toArray()['model'])->toBe('jev-test')
        ->and($override->body()->toArray()['model'])->toBe('jev-override');
});

it('rejects TypeSafe score cardinality before producing an HTTP request', function () {
    $levels = array_map(static fn (int $level): string => "Level {$level}", range(0, 10));
    $request = new DecisionRequest(
        input: 'state',
        questions: Questions::of(new Score('too-many', ScoreLevels::of(...$levels))),
    );

    expect(fn () => typesafeRequestAdapter()->toHttpClientRequest($request))
        ->toThrow(InvalidArgumentException::class, 'between 2 and 10 levels');
});

it('preflights missing credentials before body rendering', function () {
    $adapter = new TypesafeRequestAdapter(new DecisionConfig(
        driver: 'typesafe',
        apiUrl: 'https://api.example.test/v1',
        apiKey: '',
        endpoint: '/systemone',
        model: 'jev-test',
    ));

    expect(fn () => $adapter->toHttpClientRequest(typesafeGoldenDomainRequest('simple_noul')))
        ->toThrow(InvalidArgumentException::class, "field 'apiKey' is missing or empty");
});

function typesafeRequestAdapter(): TypesafeRequestAdapter
{
    return new TypesafeRequestAdapter(new DecisionConfig(
        driver: 'typesafe',
        apiUrl: 'https://api.example.test/v1/',
        apiKey: 'synthetic-test-key',
        endpoint: '/systemone',
        model: 'jev-test',
    ));
}

function typesafeGoldenRequests(): stdClass
{
    $json = file_get_contents(TYPESAFE_GOLDEN_REQUESTS);
    if (! is_string($json)) {
        throw new RuntimeException('TypeSafe golden request fixture cannot be read.');
    }

    $fixture = json_decode($json, associative: false, flags: JSON_THROW_ON_ERROR);
    if (! $fixture instanceof stdClass) {
        throw new RuntimeException('TypeSafe golden request fixture must be an object.');
    }

    return $fixture;
}

function typesafeGoldenDomainRequest(string $case): DecisionRequest
{
    return match ($case) {
        'simple_noul' => new DecisionRequest(
            input: 'Our payment integration is down.',
            questions: Questions::of(new Noul(
                id: 'urgent',
                instructions: 'Does this require immediate attention?',
                criteria: new NoulCriteria(
                    true: 'Immediate action is required',
                    false: 'Normal handling is sufficient',
                ),
            )),
            model: 'jev-override',
        ),
        'structured_mixed_numeric_ids' => new DecisionRequest(
            input: JsonContent::fromJson('{"elements":{"0":{"label":"Submit"},"1":{"label":"Cancel"}},"history":[]}'),
            questions: Questions::of(
                new Choice(
                    id: '0',
                    options: ChoiceOptions::of(
                        new ChoiceOption('0', JsonContent::object(['role' => 'button', 'enabled' => true])),
                        new ChoiceOption('1'),
                    ),
                    instructions: JsonContent::object(['task' => 'Pick an element', 'context' => []]),
                ),
                new Score(
                    id: '1',
                    levels: ScoreLevels::of(
                        'No impact',
                        JsonContent::object(['label' => 'Work blocked', 'reasons' => ['checkout']]),
                    ),
                ),
                new Noul(
                    id: 'safe',
                    criteria: new NoulCriteria(
                        true: JsonContent::object(['meaning' => 'safe to proceed']),
                    ),
                ),
            ),
        ),
        'list_state_choice' => new DecisionRequest(
            input: JsonContent::list(['first', (object) ['kind' => 'second']]),
            questions: Questions::of(new Choice(
                id: 'route',
                options: ChoiceOptions::of(
                    new ChoiceOption('alpha', 'First path'),
                    new ChoiceOption('beta', 'Second path'),
                ),
            )),
        ),
        default => throw new InvalidArgumentException("Unknown TypeSafe golden request case '{$case}'."),
    };
}
