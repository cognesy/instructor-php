<?php declare(strict_types=1);

use Cognesy\Events\Dispatchers\EventDispatcher;
use Cognesy\Http\Config\HttpClientConfig;
use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\Mock\MockHttpDriver;
use Cognesy\Http\Events\HttpClientBuilt;

it('dispatches build events on the provided event bus', function () {
    $events = new EventDispatcher('test.http-client.builder.graph');
    $captured = [];
    $events->wiretap(static function (object $event) use (&$captured): void {
        $captured[] = $event::class;
    });

    (new HttpClientBuilder(events: $events))
        ->withConfig(new HttpClientConfig(driver: 'mock'))
        ->withDriver(new MockHttpDriver($events))
        ->create();

    expect($captured)->toContain(HttpClientBuilt::class);
});
