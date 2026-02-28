<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('registers and unregisters custom inference driver factories', function () {
    $name = 'custom-state-test';
    InferenceDriverFactory::registerDriver($name, fn($config, $httpClient, $events) => new FakeInferenceDriver());

    expect(InferenceDriverFactory::hasDriver($name))->toBeTrue();
    expect(InferenceDriverFactory::registeredDrivers())->toContain($name);

    InferenceDriverFactory::unregisterDriver($name);

    expect(InferenceDriverFactory::hasDriver($name))->toBeFalse();
    expect(InferenceDriverFactory::registeredDrivers())->not->toContain($name);
});

it('reset clears all custom driver registrations', function () {
    InferenceDriverFactory::registerDriver('custom-a', fn($config, $httpClient, $events) => new FakeInferenceDriver());
    InferenceDriverFactory::registerDriver('custom-b', fn($config, $httpClient, $events) => new FakeInferenceDriver());

    expect(InferenceDriverFactory::registeredDrivers())->toContain('custom-a');
    expect(InferenceDriverFactory::registeredDrivers())->toContain('custom-b');

    InferenceDriverFactory::resetDrivers();

    expect(InferenceDriverFactory::registeredDrivers())->toBe([]);
});

it('cannot build unregistered custom driver after reset', function () {
    $config = new LLMConfig(
        apiUrl: 'https://example.com',
        apiKey: 'KEY',
        endpoint: '/v1/messages',
        model: 'test-model',
        driver: 'custom-unregistered',
    );
    $factory = new InferenceDriverFactory(new EventDispatcher());
    $httpClient = (new HttpClientBuilder())->create();

    InferenceDriverFactory::registerDriver('custom-unregistered', fn($cfg, $httpClient, $events) => new FakeInferenceDriver());
    InferenceDriverFactory::resetDrivers();

    expect(fn() => $factory->makeDriver($config, $httpClient))
        ->toThrow(InvalidArgumentException::class, 'custom-unregistered');
});
