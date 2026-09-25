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
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('checks deployment facts and decodes one live mixed Jeff decision', function (): void {
    if (Env::get('POLYGLOT_JEFF_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_JEFF_LIVE=1 to run the Jeff live smoke.');
    }

    $apiUrl = Env::get('JEFF_API_URL');
    if (! is_string($apiUrl) || trim($apiUrl) === '') {
        throw new RuntimeException('JEFF_API_URL is required when POLYGLOT_JEFF_LIVE=1.');
    }
    $apiKey = Env::get('JEFF_API_KEY');
    $apiKey = is_string($apiKey) ? $apiKey : '';
    $model = Env::get('JEFF_MODEL_NAME');
    $model = is_string($model) && trim($model) !== '' ? $model : 'gliformer-large-v1';
    $events = new EventDispatcher('polyglot.jeff.live');
    $http = (new HttpClientBuilder($events))
        ->withConfig(new HttpClientConfig(
            driver: 'symfony',
            connectTimeout: 5,
            requestTimeout: 30,
            idleTimeout: 30,
            httpVersion: '1.1',
        ))
        ->create();

    $health = jeffLiveGet($http, $apiUrl, '/healthz', $apiKey);
    $stats = jeffLiveGet($http, $apiUrl, '/stats', $apiKey);
    $models = jeffLiveGet($http, $apiUrl, '/v1/models', $apiKey);
    expect($health->ok ?? null)->toBeTrue()
        ->and((float) ($stats->temperature ?? NAN))->toBe(1.0)
        ->and(json_encode($models, JSON_THROW_ON_ERROR))->toContain($model);

    $runtime = DecisionRuntime::fromConfig(
        config: new DecisionConfig(
            driver: 'jeff',
            apiUrl: $apiUrl,
            apiKey: $apiKey,
            endpoint: '/v1/systemone',
            model: $model,
        ),
        events: $events,
        httpClient: $http,
    );
    $response = $runtime->create(new DecisionRequest(
        input: 'A duplicate charge blocked checkout and needs attention today.',
        questions: Questions::of(
            new Noul('billing', 'Is this about billing?'),
            new Choice('route', ChoiceOptions::of(
                new ChoiceOption('billing'),
                new ChoiceOption('technical'),
            )),
            new Score('urgency', ScoreLevels::of('can wait', 'today')),
        ),
    ))->response();

    expect($response->model())->not->toBe('')
        ->and($response->answers()->count())->toBe(3)
        ->and($response->answers()->score('urgency')->value())->toBeGreaterThanOrEqual(0.0)
        ->and($response->answers()->score('urgency')->value())->toBeLessThanOrEqual(1.0);
})->group('jeff-live');

function jeffLiveGet(
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
        url: rtrim($apiUrl, '/').$path,
        method: 'GET',
        headers: $headers,
        body: '',
        options: [],
    ))->get();
    if ($response->statusCode() >= 400) {
        throw new RuntimeException("Jeff live check failed for {$path} (HTTP {$response->statusCode()}).");
    }
    $body = json_decode($response->body(), associative: false, flags: JSON_THROW_ON_ERROR);
    if (! $body instanceof stdClass) {
        throw new RuntimeException("Jeff live check for {$path} did not return an object.");
    }

    return $body;
}
