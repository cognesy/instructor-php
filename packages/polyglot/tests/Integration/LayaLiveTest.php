<?php

declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Contracts\CanSendHttpRequests;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('checks deployment facts and decodes one live mixed Laya decision', function (): void {
    if (Env::get('POLYGLOT_LAYA_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_LAYA_LIVE=1 to run the external Laya service smoke.');
    }

    $apiUrl = Env::get('LAYA_API_URL');
    if (!is_string($apiUrl) || trim($apiUrl) === '') {
        throw new RuntimeException('LAYA_API_URL is required when POLYGLOT_LAYA_LIVE=1.');
    }
    $apiKey = Env::get('LAYA_API_KEY');
    $apiKey = is_string($apiKey) ? $apiKey : '';
    $model = Env::get('LAYA_MODEL_ROUTE');
    $model = is_string($model) && trim($model) !== '' ? $model : 'laya-typed-decisions';
    $events = new EventDispatcher('polyglot.laya.live');
    $http = (new HttpClientBuilder($events))
        ->withConfig(new HttpClientConfig(
            driver: 'symfony',
            connectTimeout: 5,
            requestTimeout: 60,
            idleTimeout: 60,
        ))
        ->create();

    $health = layaLiveGet($http, $apiUrl, '/healthz', $apiKey);
    $ready = layaLiveGet($http, $apiUrl, '/readyz', $apiKey);
    $models = layaLiveGet($http, $apiUrl, '/v1/models', $apiKey);
    $stats = layaLiveGet($http, $apiUrl, '/stats', $apiKey);
    expect($health->status ?? null)->toBe('ok')
        ->and($ready->ready ?? null)->toBeTrue()
        ->and($models->models[0]->route ?? null)->toBe($model)
        ->and($models->models[0]->revision ?? null)->not->toBe('')
        ->and($stats->load_count ?? null)->toBe(1);

    $runtime = DecisionRuntime::fromConfig(
        config: new DecisionConfig(
            driver: 'laya',
            apiUrl: $apiUrl,
            apiKey: $apiKey,
            endpoint: '/v1/systemone',
            model: $model,
        ),
        events: $events,
        httpClient: $http,
    );
    $response = $runtime->create(new DecisionRequest(
        input: JsonContent::object([
            'message' => 'A duplicate charge blocked checkout and needs attention today.',
        ]),
        questions: Questions::of(
            new Noul('billing', 'Is this about billing?'),
            new Choice('route', ChoiceOptions::of(
                new ChoiceOption('billing'),
                new ChoiceOption('technical'),
            ), 'Which team should handle this?'),
            new Score('urgency', ScoreLevels::of('can wait', 'today'), 'How urgent is this?'),
        ),
    ))->response();

    expect($response->model())->toStartWith("{$model}@")
        ->and($response->answers()->count())->toBe(3)
        ->and($response->answers()->choice('route')->signals()->modelActionProbability())
        ->toBeGreaterThanOrEqual(0.0)
        ->and($response->answers()->noul('billing')->signals()->modelActionProbability())
        ->toBeGreaterThanOrEqual(0.0)
        ->and($response->answers()->score('urgency')->signals()->modelActionProbability())
        ->toBeGreaterThanOrEqual(0.0);
})->group('laya-live');

function layaLiveGet(
    CanSendHttpRequests $http,
    string $apiUrl,
    string $path,
    string $apiKey,
): stdClass {
    $headers = match (trim($apiKey)) {
        '' => ['Accept' => 'application/json'],
        default => ['Accept' => 'application/json', 'Authorization' => "Bearer {$apiKey}"],
    };
    $response = $http->send(new HttpRequest(
        url: rtrim($apiUrl, '/') . $path,
        method: 'GET',
        headers: $headers,
        body: '',
        options: [],
    ))->get();
    if ($response->statusCode() >= 400) {
        throw new RuntimeException("Laya live check failed for {$path} (HTTP {$response->statusCode()}).");
    }
    $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
    if (!$body instanceof stdClass) {
        throw new RuntimeException("Laya live check for {$path} did not return an object.");
    }

    return $body;
}
