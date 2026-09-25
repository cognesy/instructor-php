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
use Cognesy\Polyglot\Decision\Drivers\Laya\LayaRequestAdapter;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('preserves text object and list state through the shared System One encoder', function (
    string|JsonContent $input,
    mixed $expected,
): void {
    $request = new DecisionRequest(
        $input,
        Questions::of(new Noul('safe', 'Is this safe?')),
    );
    $http = (new LayaRequestAdapter(layaRequestConfig()))->toHttpClientRequest($request);

    expect($http->body()->toArray()['state'])->toEqual($expected)
        ->and($http->body()->toArray()['model'])->toBe('laya-typed-decisions')
        ->and($http->headers())->not->toHaveKey('Authorization');
})->with([
    'text' => ['state', 'state'],
    'object' => [JsonContent::object(['message' => 'state']), ['message' => 'state']],
    'list' => [JsonContent::list([['role' => 'user', 'content' => 'state']]), [[
        'role' => 'user',
        'content' => 'state',
    ]]],
]);

it('encodes every primitive and optional bearer authentication', function (): void {
    $request = new DecisionRequest('state', Questions::of(
        new Choice('route', ChoiceOptions::of(
            new ChoiceOption('billing', JsonContent::object(['kind' => 'charge'])),
            new ChoiceOption('technical'),
        ), JsonContent::object(['task' => 'route'])),
        new Noul('safe', 'Is this safe?', new NoulCriteria(true: 'safe', false: 'unsafe')),
        new Score('impact', ScoreLevels::of('low', JsonContent::object(['label' => 'high'])), 'Impact?'),
    ));
    $http = (new LayaRequestAdapter(layaRequestConfig('test-key')))->toHttpClientRequest($request);
    $questions = $http->body()->toArray()['questions'];

    expect($http->url())->toBe('http://127.0.0.1:8091/v1/systemone')
        ->and($http->headers()['Authorization'])->toBe('Bearer test-key')
        ->and($questions['route']['criteria']['billing'])->toBe(['kind' => 'charge'])
        ->and($questions['safe']['criteria'])->toBe(['true' => 'safe', 'false' => 'unsafe'])
        ->and($questions['impact']['criteria'][1])->toBe(['label' => 'high']);
});

function layaRequestConfig(string $apiKey = ''): DecisionConfig {
    return new DecisionConfig(
        driver: 'laya',
        apiUrl: 'http://127.0.0.1:8091',
        apiKey: $apiKey,
        endpoint: '/v1/systemone',
        model: 'laya-typed-decisions',
    );
}
