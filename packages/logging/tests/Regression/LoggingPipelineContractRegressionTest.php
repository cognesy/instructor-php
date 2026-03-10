<?php declare(strict_types=1);

use Cognesy\Events\Event;
use Cognesy\Logging\LogEntry;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Writers\CallableWriter;
use Psr\Log\LogLevel;

it('uses event time for log entries when no formatter is configured', function () {
    $logged = [];
    $event = new Event(['scope' => 'timestamp']);
    $eventTime = $event->createdAt;

    sleep(1);

    LoggingPipeline::create()
        ->write(CallableWriter::create(function (LogEntry $entry) use (&$logged): void {
            $logged[] = $entry;
        }))
        ->build()($event);

    expect($logged)->toHaveCount(1)
        ->and($logged[0]->timestamp)->toBe($eventTime);
});

it('snapshots builder state when build is called', function () {
    $messages = [];

    $builder = LoggingPipeline::create()
        ->write(CallableWriter::create(function (LogEntry $entry) use (&$messages): void {
            $messages[] = $entry->message;
        }));

    $pipeline = $builder->build();

    $builder->write(CallableWriter::create(function (LogEntry $entry) use (&$messages): void {
        $messages[] = 'later:' . $entry->message;
    }));

    $pipeline(new Event(['scope' => 'immutability']));

    expect($messages)->toBe(['Event']);
});
