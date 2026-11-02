<?php

declare(strict_types=1);

use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Models\ModelCatalog;
use Cognesy\Polyglot\Inference\Reasoning\ReasoningEffort;
use Cognesy\Tell\Core\Agent\TellToolReasoningRoute;

function tellRouteCatalog(string $driver): ModelCatalog {
    return ModelCatalog::fromArray([
        'version' => 'route-v1',
        'models' => [[
            'driver' => $driver,
            'model' => 'reasoner',
            'capabilities' => [
                'toolsWithReasoning' => 'unsupported',
                'reasoning' => [
                    'selections' => ['disabled', 'effort'],
                    'efforts' => [['requested' => 'low', 'provider' => 'low']],
                    'default' => 'enabled',
                ],
            ],
        ]],
    ]);
}

it('rejects reasoning with tools when no Responses route accepts it', function (string $driver): void {
    $route = new TellToolReasoningRoute(tellRouteCatalog($driver));

    expect(fn () => $route->apply(new LLMConfig(driver: $driver, model: 'reasoner'), ReasoningEffort::Low))
        ->toThrow(InvalidArgumentException::class, 'does not accept tools with enabled reasoning');
})->with(['driver without a Responses route' => 'custom', 'Responses record missing' => 'openai']);

it('keeps the connection when the run does not keep reasoning on', function (): void {
    $config = new LLMConfig(driver: 'custom', model: 'reasoner');

    expect((new TellToolReasoningRoute(tellRouteCatalog('custom')))->apply($config, null))->toBe($config);
});
