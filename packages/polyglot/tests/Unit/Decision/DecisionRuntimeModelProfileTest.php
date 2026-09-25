<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Polyglot\Decision\Answers\NoulAnswer;
use Cognesy\Polyglot\Decision\Collections\Answers;
use Cognesy\Polyglot\Decision\Collections\Questions;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\DecisionRuntime;
use Cognesy\Polyglot\Decision\Events\DecisionStarted;
use Cognesy\Polyglot\Decision\Models\ModelCatalog;
use Cognesy\Polyglot\Decision\Questions\Noul;

it('keeps ordinary decision execution independent of model knowledge', function (): void {
    $runtime = new DecisionRuntime(
        driver: new DecisionProfileTestDriver,
        events: new EventDispatcher,
        defaultModel: 'jev-latest',
        driverName: 'typesafe',
    );

    $request = $runtime->create(decisionProfileTestRequest())->request();

    expect($request->model())->toBe('jev-latest')
        ->and($request->modelProfile())->toBeNull();
});

it('attaches decision facts for the effective route and keeps them transient', function (): void {
    $runtime = new DecisionRuntime(
        driver: new DecisionProfileTestDriver,
        events: new EventDispatcher,
        defaultModel: 'jev-latest',
        driverName: 'typesafe',
        models: ModelCatalog::discover(),
    );

    $known = $runtime->create(decisionProfileTestRequest())->request();
    $unknown = $runtime->create(decisionProfileTestRequest('jev-preview'))->request();

    expect($known->modelProfile()?->model)->toBe('jev-latest')
        ->and($known->modelProfile()?->maxRequestTokens)->toBe(64000)
        ->and($known->toArray())->not->toHaveKeys(['modelProfile', 'model_profile'])
        ->and($unknown->modelProfile()?->model)->toBe('jev-preview')
        ->and($unknown->modelProfile()?->maxRequestTokens)->toBeNull();
});

it('projects only decision model identity and catalog revision into lifecycle metadata', function (): void {
    $events = new EventDispatcher;
    $captured = null;
    $events->addListener(
        DecisionStarted::class,
        static function (DecisionStarted $event) use (&$captured): void {
            $captured = $event;
        },
    );
    $runtime = new DecisionRuntime(
        driver: new DecisionProfileTestDriver,
        events: $events,
        defaultModel: 'jev-latest',
        driverName: 'typesafe',
        models: ModelCatalog::discover(),
    );

    $runtime->create(decisionProfileTestRequest())->response();

    expect($captured)->toBeInstanceOf(DecisionStarted::class)
        ->and($captured->data)->toMatchArray([
            'model' => 'jev-latest',
            'modelKey' => 'typesafe/jev-latest',
            'modelCatalogVersion' => '2026-09-18',
            'decisionPrimitiveSupport' => [
                'choice' => 'native',
                'noul' => 'native',
                'score' => 'native',
            ],
        ])
        ->and($captured->data)->not->toHaveKeys(['modelProfile', 'pricing']);
});

function decisionProfileTestRequest(?string $model = null): DecisionRequest
{
    return new DecisionRequest(
        input: 'state',
        questions: Questions::of(new Noul('safe', 'Is this safe?')),
        model: $model,
    );
}

final class DecisionProfileTestDriver implements CanProcessDecisionRequest
{
    public function handle(DecisionRequest $request): DecisionResponse
    {
        return new DecisionResponse(
            answers: Answers::of(new NoulAnswer('safe', 0.9)),
            model: $request->model() ?? 'jev-latest',
        );
    }
}
