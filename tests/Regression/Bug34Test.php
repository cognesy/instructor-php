<?php

use Cognesy\LLM\Http\Data\HttpClientConfig;
use Cognesy\LLM\Http\Data\HttpClientRequest;
use Cognesy\LLM\Http\Drivers\GuzzleDriver;
use Cognesy\LLM\Http\Enums\HttpClientType;
use Cognesy\Utils\Debug\Debug;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;

beforeEach(function () {
    // Reset Debug state before each test
    Debug::disable();
});

test('custom http client is used when debugging is disabled', function () {
    // Arrange
    $config = new HttpClientConfig(
        httpClientType: HttpClientType::Guzzle,
        connectTimeout: 3,
        requestTimeout: 30
    );

    // Create a mock handler with a response
    $mock = new MockHandler([
        new Response(200, [], 'Hello, World!')
    ]);

    $container = [];
    $history = Middleware::history($container);

    $stack = HandlerStack::create($mock);
    $stack->push($history);

    // Create a custom client with specific configuration
    $customClient = new Client([
        'base_uri' => 'https://api.example.com',
        'handler' => $stack
    ]);

    // Act
    $driver = new GuzzleDriver($config, $customClient);

    // Make a request to test which client instance is used
    $response = $driver->handle(new HttpClientRequest(
        url: '/test', // Using relative path to test base_uri
        method: 'GET',
        headers: [],
        body: [],
        options: []
    ));

    // Assert
    expect($container)->toHaveCount(1);
    // Verify the request used the custom client's base URI
    expect($container[0]['request']->getUri()->getHost())->toBe('api.example.com');
    // Verify the mock response was received
    expect($response->getContents())->toBe('Hello, World!');
});

test('default client is created when no custom client is provided and debugging is disabled', function () {
    // Arrange
    $config = new HttpClientConfig(
        httpClientType: HttpClientType::Guzzle,
        connectTimeout: 3,
        requestTimeout: 30
    );

    // Act
    $driver = new GuzzleDriver($config);

    // Use reflection to access protected client property
    $reflection = new ReflectionClass($driver);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $client = $clientProperty->getValue($driver);

    // Assert
    expect($client)->toBeInstanceOf(Client::class);

    // Verify it's a basic client without debug stack
    $handlerProperty = (new ReflectionClass($client))->getProperty('config');
    $handlerProperty->setAccessible(true);
    $clientConfig = $handlerProperty->getValue($client);

    expect($clientConfig)->not->toHaveKey('debug');
});

test('debug client is created when debugging is enabled and no custom client is provided', function () {
    // Arrange
    Debug::enable();

    $config = new HttpClientConfig(
        httpClientType: HttpClientType::Guzzle,
        connectTimeout: 3,
        requestTimeout: 30
    );

    // Act
    $driver = new GuzzleDriver($config);

    // Use reflection to access protected client property
    $reflection = new ReflectionClass($driver);
    $clientProperty = $reflection->getProperty('client');
    $clientProperty->setAccessible(true);
    $client = $clientProperty->getValue($driver);

    // Assert
    expect($client)->toBeInstanceOf(Client::class);

    // Verify it has a debug stack
    $handlerProperty = (new ReflectionClass($client))->getProperty('config');
    $handlerProperty->setAccessible(true);
    $clientConfig = $handlerProperty->getValue($client);

    expect($clientConfig['handler'])->toBeInstanceOf(HandlerStack::class);

    // Reset Debug state
    Debug::disable();
});

test('client timeout configuration is properly set', function () {
    // Arrange
    $container = [];
    $history = Middleware::history($container);

    $mock = new MockHandler([
        new Response(200, [], 'Hello, World!')
    ]);

    $stack = HandlerStack::create($mock);
    $stack->push($history);

    // Create a custom client with the mock handler
    $customClient = new Client([
        'handler' => $stack
    ]);

    $config = new HttpClientConfig(
        httpClientType: HttpClientType::Guzzle,
        connectTimeout: 5,
        requestTimeout: 10
    );

    // Act
    $driver = new GuzzleDriver($config, $customClient);

    $driver->handle(new HttpClientRequest(
        url: 'https://api.test.com/endpoint',
        method: 'GET',
        headers: [],
        body: [],
        options: [],
    ));

    // Assert
    expect($container[0]['options'])->toHaveKey('connect_timeout', 5);
    expect($container[0]['options'])->toHaveKey('timeout', 10);
});