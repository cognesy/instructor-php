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
use Cognesy\Polyglot\Decision\Models\ModelCatalog;
use Cognesy\Polyglot\Decision\Questions\Choice;
use Cognesy\Polyglot\Decision\Questions\Noul;
use Cognesy\Polyglot\Decision\Questions\Score;

it('decodes one bounded live Perplexity decision through the bundled preset', function (string $preset): void {
    if (Env::get('POLYGLOT_PERPLEXITY_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_PERPLEXITY_LIVE=1 to run the Perplexity live smoke.');
    }
    foreach (['PERPLEXITY_API_KEY'] as $name) {
        $value = Env::get($name);
        if (! is_string($value) || trim($value) === '') {
            throw new RuntimeException("{$name} is required when POLYGLOT_PERPLEXITY_LIVE=1.");
        }
    }

    $events = new EventDispatcher('polyglot.perplexity.live');
    $http = (new HttpClientBuilder($events))
        ->withConfig(new HttpClientConfig(
            driver: 'symfony',
            connectTimeout: 5,
            requestTimeout: 60,
            idleTimeout: 60,
        ))
        ->create();
    $runtime = DecisionRuntime::fromConfig(
        config: DecisionConfig::fromPreset($preset),
        events: $events,
        httpClient: $http,
        models: ModelCatalog::discover(),
    );

    $response = $runtime->create(new DecisionRequest(
        input: 'Checkout has been failing for every customer for the last hour.',
        questions: Questions::of(
            new Noul('urgent', 'Is this support request urgent?'),
            new Choice(
                id: 'team',
                options: ChoiceOptions::of(
                    new ChoiceOption('billing', 'Payments, invoices, and refunds'),
                    new ChoiceOption('technical', 'Outages, errors, and configuration'),
                    new ChoiceOption('sales', 'Plans and upgrades'),
                ),
                instructions: 'Which team should handle this request?',
            ),
            new Score(
                id: 'severity',
                levels: ScoreLevels::of('No impact', 'Minor', 'Major', 'Critical'),
                instructions: 'How severe is the customer impact?',
            ),
        ),
    ))->response();

    expect($response->model())->toBe('pplx-decider-v1-27b')
        ->and($response->answers()->noul('urgent')->probability())->toBeGreaterThan(0.5)
        ->and($response->answers()->choice('team')->value())->toBe('technical')
        ->and($response->answers()->score('severity')->value())->toBeGreaterThan(1.5)
        ->and($response->usage()->inputTokens())->toBeGreaterThan(0)
        ->and($response->providerRequestId()->toString())->not->toBe('');
})->with(['perplexity']);
