<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Data\HttpRequest;
use Cognesy\Http\Drivers\Curl\CurlDriver;
use Cognesy\Http\Exceptions\TimeoutException;

it('uses configurable streamHeaderTimeout for curl streaming header priming', function () {
    if (!extension_loaded('curl')) {
        $this->markTestSkipped('cURL extension not available');
    }

    $driver = new CurlDriver(
        config: new HttpClientConfig(driver: 'curl', streamHeaderTimeout: 0),
        events: new EventDispatcher(),
    );

    $request = new HttpRequest(
        url: 'http://127.0.0.1:65535',
        method: 'GET',
        headers: [],
        body: '',
        options: ['stream' => true],
    );

    expect(fn() => $driver->handle($request))
        ->toThrow(TimeoutException::class, 'Timed out waiting for response headers');
});
