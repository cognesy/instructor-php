<?php

declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Polyglot\Decision\Config\DecisionConfig;
use Cognesy\Polyglot\Decision\Contracts\CanProcessDecisionRequest;
use Cognesy\Polyglot\Decision\Creation\DecisionDriverRegistry;
use Cognesy\Polyglot\Decision\Data\DecisionRequest;
use Cognesy\Polyglot\Decision\Data\DecisionResponse;
use Cognesy\Polyglot\Decision\DecisionProvider;

it('composes explicit config driver and model without mutating the provider', function () {
    $config = new DecisionConfig(model: 'base-model');
    $driver = new class implements CanProcessDecisionRequest
    {
        #[Override]
        public function handle(DecisionRequest $request): DecisionResponse
        {
            throw new LogicException('Not executed by provider composition tests.');
        }
    };
    $provider = DecisionProvider::fromDecisionConfig($config);
    $configured = $provider->withDriver($driver)->withModel('override-model');

    expect($provider->resolveConfig())->toBe($config)
        ->and($provider->explicitDecisionDriver())->toBeNull()
        ->and($configured->resolveConfig()->model)->toBe('override-model')
        ->and($configured->explicitDecisionDriver())->toBe($driver);
});

it('registers typesafe by default and supports explicit driver factories', function () {
    $driver = new class implements CanProcessDecisionRequest
    {
        #[Override]
        public function handle(DecisionRequest $request): DecisionResponse
        {
            throw new LogicException('Not executed by registry composition tests.');
        }
    };
    $registry = DecisionDriverRegistry::default()->withDriver(
        'test',
        static fn () => $driver,
    );
    $http = (new HttpClientBuilder)->withDriver(new MockHttpDriver)->create();

    expect($registry->has('typesafe'))->toBeTrue()
        ->and($registry->driverNames())->toBe(['typesafe', 'test'])
        ->and($registry->makeDriver('test', new DecisionConfig, $http, new EventDispatcher))->toBe($driver)
        ->and($registry->withoutDriver('typesafe')->has('typesafe'))->toBeFalse()
        ->and(fn () => $registry->makeDriver('missing', new DecisionConfig, $http, new EventDispatcher))
        ->toThrow(InvalidArgumentException::class, 'missing decision driver: missing');
});

it('rejects a custom factory returning the wrong contract', function () {
    $registry = DecisionDriverRegistry::fromArray(['invalid' => static fn () => new stdClass]);
    $http = (new HttpClientBuilder)->withDriver(new MockHttpDriver)->create();

    expect(fn () => $registry->makeDriver('invalid', new DecisionConfig, $http, new EventDispatcher))
        ->toThrow(InvalidArgumentException::class, 'Decision driver must implement');
});
