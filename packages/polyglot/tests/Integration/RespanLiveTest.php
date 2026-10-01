<?php

declare(strict_types=1);

use Cognesy\Config\Env;
use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\JsonContent;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Exceptions\DecisionAccessException;
use Cognesy\Polyglot\Decision\Models\ModelCatalog;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('decodes one bounded live RESPAN decision through the bundled preset', function (): void {
    if (Env::get('POLYGLOT_RESPAN_LIVE') !== '1') {
        test()->markTestSkipped('Set POLYGLOT_RESPAN_LIVE=1 to run the RESPAN live smoke.');
    }

    $apiKey = Env::get('RESPAN_API_KEY');
    if (! is_string($apiKey) || trim($apiKey) === '') {
        Env::set(dirname(__DIR__, 4));
        $apiKey = Env::get('RESPAN_API_KEY');
    }
    if (! is_string($apiKey) || trim($apiKey) === '') {
        throw new RuntimeException('RESPAN_API_KEY is required when POLYGLOT_RESPAN_LIVE=1.');
    }

    $events = new EventDispatcher('polyglot.respan.live');
    $http = (new HttpClientBuilder($events))
        ->withConfig(new HttpClientConfig(
            driver: 'symfony',
            connectTimeout: 5,
            requestTimeout: 30,
            idleTimeout: 30,
        ))
        ->create();
    $runtime = DecisionRuntime::fromConfig(
        config: DecisionConfig::fromPreset('respan'),
        events: $events,
        httpClient: $http,
        models: ModelCatalog::discover(),
    );

    try {
        $response = $runtime->create(new DecisionRequest(
            input: JsonContent::object([
                'input' => [[
                    'role' => 'user',
                    'content' => 'This is the third time my order is late.',
                ]],
                'output' => [
                    'role' => 'assistant',
                    'content' => 'I am sorry, let me check on that for you.',
                ],
            ]),
            questions: Questions::of(
                new Noul('frustrated', 'The user expresses frustration or anger.'),
                new Noul('apology', 'The assistant apologizes for a problem.'),
            ),
        ))->response();
    } catch (DecisionAccessException $exception) {
        fwrite(STDOUT, sprintf(
            "\n[respan-live] timestamp=%s endpoint=https://api.respan.ai/api/v1/scores outcome=access-denied status=%s\n",
            gmdate(DATE_ATOM),
            $exception->statusCode === null ? 'unknown' : (string) $exception->statusCode,
        ));
        test()->markTestSkipped('RESPAN Span-01 is not enabled for this organization.');
    }

    $answers = $response->answers();
    foreach (['frustrated', 'apology'] as $id) {
        $probabilities = $answers->noul($id)->probabilities();
        expect($probabilities->positive())->toBeBetween(0.0, 1.0)
            ->and($probabilities->negative())->toBeBetween(0.0, 1.0)
            ->and($probabilities->unknown())->toBeBetween(0.0, 1.0)
            ->and(abs(array_sum($probabilities->toArray()) - 1.0))->toBeLessThanOrEqual(0.02);
    }
    expect($response->model())->toBeIn(['span-01-free', 'span-01-pro'])
        ->and($response->providerRequestId()->toString())->not->toBe('')
        ->and($response->usage()->outputTokens())->toBeNull();

    fwrite(STDOUT, sprintf(
        "\n[respan-live] timestamp=%s endpoint=https://api.respan.ai/api/v1/scores outcome=success model=%s input_tokens=%s\n",
        gmdate(DATE_ATOM),
        $response->model(),
        $response->usage()->inputTokens() === null
            ? 'not-reported'
            : (string) $response->usage()->inputTokens(),
    ));
})->group('respan-live');
