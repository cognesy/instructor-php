<?php

declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
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

it('decodes one bounded live mixed classifier.dev decision', function (): void {
    if (Env::get('POLYGLOT_CLASSIFIER_DEV_LIVE') !== '1') {
        test()->markTestSkipped(
            'Set POLYGLOT_CLASSIFIER_DEV_LIVE=1 to run the classifier.dev live smoke.',
        );
    }

    $apiKey = Env::get('CLASSIFIER_API_KEY');
    $apiKey = is_string($apiKey) ? $apiKey : '';
    $events = new EventDispatcher('polyglot.classifier-dev.live');
    $http = (new HttpClientBuilder($events))
        ->withConfig(new HttpClientConfig(
            driver: 'symfony',
            connectTimeout: 5,
            requestTimeout: 30,
            idleTimeout: 30,
        ))
        ->create();
    $runtime = DecisionRuntime::fromConfig(
        config: new DecisionConfig(
            driver: 'classifier-dev',
            apiUrl: 'https://classifier.dev',
            apiKey: $apiKey,
            endpoint: '/v1/classify',
            model: 'fast',
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
        ->and($response->answers()->choice('route')->value())->toBeIn(['billing', 'technical'])
        ->and($response->answers()->score('urgency')->value())->toBeGreaterThanOrEqual(0.0)
        ->and($response->answers()->score('urgency')->value())->toBeLessThanOrEqual(1.0)
        ->and($response->usage()->totalTokens())->toBeNull();
})->group('classifier-dev-live');
