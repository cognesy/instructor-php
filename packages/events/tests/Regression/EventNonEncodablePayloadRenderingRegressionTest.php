<?php declare(strict_types=1);

use Cognesy\Events\Event;

it('uses a non-blank fallback message for non-encodable payloads', function () {
    $event = new Event(['bad' => "\xB1\x31"]);

    expect((string) $event)->toBe('[unserializable event payload]')
        ->and($event->asLog())->toContain('[unserializable event payload]')
        ->and($event->asConsole())->toContain('[unserializable event payload]');
});
