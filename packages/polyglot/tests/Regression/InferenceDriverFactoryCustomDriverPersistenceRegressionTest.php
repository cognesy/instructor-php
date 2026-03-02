<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Polyglot\Inference\Config\LLMConfig;
use Cognesy\Polyglot\Inference\Creation\InferenceDriverFactory;
use Cognesy\Polyglot\Tests\Support\FakeInferenceDriver;

it('keeps custom drivers registered via static API available across multiple factory instances', function () {
    InferenceDriverFactory::registerDriver(
        'custom-persistent',
        fn($config, $httpClient, $events) => new FakeInferenceDriver(),
    );

    $config = new LLMConfig(
        apiUrl: 'https://example.com',
        apiKey: 'KEY',
        endpoint: '/v1/messages',
        model: 'test-model',
        driver: 'custom-persistent',
    );
    $events = new EventDispatcher();
    $httpClient = (new HttpClientBuilder())->create();

    $firstFactory = new InferenceDriverFactory($events);
    $firstDriver = $firstFactory->makeDriver($config, $httpClient);
    expect($firstDriver)->toBeInstanceOf(FakeInferenceDriver::class);

    $secondFactory = new InferenceDriverFactory($events);
    $secondDriver = $secondFactory->makeDriver($config, $httpClient);
    expect($secondDriver)->toBeInstanceOf(FakeInferenceDriver::class);
});
