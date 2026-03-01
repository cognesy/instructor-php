<?php declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\LogContext;

it('interpolates brace-qualified event data placeholders', function () {
    $event = new Event(['method' => 'GET', 'url' => '/a']);
    $context = LogContext::fromEvent($event);
    $formatter = new MessageTemplateFormatter(defaultTemplate: 'HTTP {method} {url}');

    $entry = $formatter($event, $context);

    expect($entry->message)->toBe('HTTP GET /a');
});

it('interpolates framework placeholders', function () {
    $event = new Event(['method' => 'GET']);
    $context = LogContext::fromEvent($event, ['framework' => ['request_id' => 'req-123']]);
    $formatter = new MessageTemplateFormatter(defaultTemplate: 'request={framework.request_id}');

    $entry = $formatter($event, $context);

    expect($entry->message)->toBe('request=req-123');
});

it('keeps unresolved placeholders unchanged', function () {
    $event = new Event(['method' => 'GET']);
    $context = LogContext::fromEvent($event);
    $formatter = new MessageTemplateFormatter(defaultTemplate: '{method} {missing}');

    $entry = $formatter($event, $context);

    expect($entry->message)->toBe('GET {missing}');
});

it('does not corrupt reserved placeholders when event data has overlapping key names', function () {
    $event = new Event(['id' => 'data-42']);
    $context = LogContext::fromEvent($event);
    $formatter = new MessageTemplateFormatter(defaultTemplate: 'event={event_id} data={id}');

    $entry = $formatter($event, $context);

    expect($entry->message)->toBe("event={$event->id} data=data-42");
});
