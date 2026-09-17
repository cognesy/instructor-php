<?php

declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Events\HttpRequestSent;
use Cognesy\Polyglot\Decision\Collections\ChoiceOptions;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Collections\ScoreLevels;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Config\DecisionRetryPolicy;
use Cognesy\Polyglot\Decision\Data\ChoiceOption;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\NoulCriteria;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('decodes one live mixed TypeSafe decision through the bundled preset', function () {
    if (Env::get('POLYGLOT_TYPESAFE_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_TYPESAFE_LIVE=1 to run the TypeSafe live smoke.');
    }

    $apiKey = Env::get('TYPESAFE_API_KEY');
    if (! is_string($apiKey) || trim($apiKey) === '') {
        Env::set(dirname(__DIR__, 4));
        $apiKey = Env::get('TYPESAFE_API_KEY');
    }
    if (! is_string($apiKey) || trim($apiKey) === '') {
        throw new RuntimeException('TYPESAFE_API_KEY is required when POLYGLOT_TYPESAFE_LIVE=1.');
    }

    $events = new EventDispatcher('polyglot.typesafe.live');
    $requestCount = 0;
    $events->addListener(HttpRequestSent::class, static function () use (&$requestCount): void {
        $requestCount++;
    });
    $httpClient = (new HttpClientBuilder($events))
        ->withConfig(new HttpClientConfig(
            driver: 'symfony',
            connectTimeout: 5,
            requestTimeout: 20,
            idleTimeout: 20,
        ))
        ->create();
    $runtime = DecisionRuntime::fromConfig(
        config: DecisionConfig::fromPreset('typesafe'),
        events: $events,
        httpClient: $httpClient,
    );
    $questions = Questions::of(
        new Noul(
            id: 'billing',
            instructions: 'Is this customer message about a billing problem?',
            criteria: new NoulCriteria(
                true: 'The message concerns a payment, charge, invoice, or refund.',
                false: 'The message does not concern billing.',
            ),
        ),
        new Choice(
            id: 'tone',
            options: ChoiceOptions::of(
                new ChoiceOption('calm'),
                new ChoiceOption('frustrated'),
                new ChoiceOption('angry'),
            ),
            instructions: 'What is the customer tone?',
        ),
        new Score(
            id: 'urgency',
            levels: ScoreLevels::of('can wait', 'this week', 'today'),
            instructions: 'How urgently should this message be handled?',
        ),
    );
    $pending = $runtime->create(new DecisionRequest(
        input: 'I was charged twice for one subscription. Please fix this today.',
        questions: $questions,
        retryPolicy: new DecisionRetryPolicy(maxAttempts: 1),
    ));

    $response = $pending->response();
    $answers = $response->answers();
    $noul = $answers->noul('billing');
    $choice = $answers->choice('tone');
    $score = $answers->score('urgency');

    expect($pending->response())->toBe($response)
        ->and($requestCount)->toBe(1)
        ->and($response->model())->not->toBe('')
        ->and($response->providerRequestId()->toString())->not->toBe('')
        ->and(array_map(static fn ($answer): string => $answer->questionId(), $answers->all()))
        ->toBe(['billing', 'tone', 'urgency'])
        ->and($noul->probability())->toBeGreaterThanOrEqual(0.0)
        ->and($noul->probability())->toBeLessThanOrEqual(1.0)
        ->and($choice->value())->toBeIn(['calm', 'frustrated', 'angry'])
        ->and($choice->probabilities()->ids())->toBe(['calm', 'frustrated', 'angry'])
        ->and($choice->confidence())->toBeGreaterThanOrEqual(0.0)
        ->and($choice->confidence())->toBeLessThanOrEqual(1.0)
        ->and($score->value())->toBeGreaterThanOrEqual(0.0)
        ->and($score->value())->toBeLessThanOrEqual(2.0)
        ->and($score->probabilities()->count())->toBe(3)
        ->and($score->legend()->toArray())->toBe(['can wait', 'this week', 'today'])
        ->and($score->confidence())->toBeGreaterThanOrEqual(0.0)
        ->and($score->confidence())->toBeLessThanOrEqual(1.0);

    $usage = $response->usage();
    expect($usage->inputTokens() === null || $usage->inputTokens() >= 0)->toBeTrue()
        ->and($usage->outputTokens() === null || $usage->outputTokens() >= 0)->toBeTrue();

    fwrite(STDOUT, sprintf(
        "\n[typesafe-live] timestamp=%s endpoint=https://api.typesafe.ai/v1/systemone model=%s primitive_count=3 outcome=success input_tokens=%s output_tokens=%s\n",
        gmdate(DATE_ATOM),
        $response->model(),
        $usage->inputTokens() === null ? 'not-reported' : (string) $usage->inputTokens(),
        $usage->outputTokens() === null ? 'not-reported' : (string) $usage->outputTokens(),
    ));
})->group('typesafe-live');
