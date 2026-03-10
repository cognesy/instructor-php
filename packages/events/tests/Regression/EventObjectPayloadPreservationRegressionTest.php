<?php declare(strict_types=1);

use Cognesy\Events\Event;

it('normalizes object payloads to public properties only for observability', function () {
    $payload = new class {
        public string $public = 'visible';
        private string $private = 'hidden';
    };

    $event = new Event($payload);

    expect($event->data)
        ->toBe(['public' => 'visible'])
        ->and($event->toArray()['data'])
        ->toBe(['public' => 'visible'])
        ->and((string) $event)
        ->toBe('{"public":"visible"}');
});
