<?php

declare(strict_types=1);

use Cognesy\Logging\LogEntry;
use Psr\Log\LogLevel;

describe('LogEntry', function () {
    it('creates entry with all properties', function () {
        $timestamp = new DateTimeImmutable();
        $entry = LogEntry::create(
            level: LogLevel::INFO,
            message: 'Test message',
            context: ['key' => 'value'],
            timestamp: $timestamp,
            channel: 'test'
        );

        expect($entry->level)->toBe(LogLevel::INFO)
            ->and($entry->message)->toBe('Test message')
            ->and($entry->context)->toBe(['key' => 'value'])
            ->and($entry->timestamp)->toBe($timestamp)
            ->and($entry->channel)->toBe('test');
    });

    it('creates entry with defaults', function () {
        $entry = LogEntry::create(LogLevel::ERROR, 'Error message');

        expect($entry->level)->toBe(LogLevel::ERROR)
            ->and($entry->message)->toBe('Error message')
            ->and($entry->context)->toBe([])
            ->and($entry->channel)->toBe('default');
    });

    it('is immutable and provides fluent API', function () {
        $original = LogEntry::create(LogLevel::INFO, 'Original');

        $modified = $original
            ->withLevel(LogLevel::ERROR)
            ->withMessage('Modified')
            ->withContext(['new' => 'data'])
            ->withChannel('modified');

        expect($original->level)->toBe(LogLevel::INFO)
            ->and($original->message)->toBe('Original')
            ->and($modified->level)->toBe(LogLevel::ERROR)
            ->and($modified->message)->toBe('Modified')
            ->and($modified->context)->toBe(['new' => 'data'])
            ->and($modified->channel)->toBe('modified');
    });

    it('checks log levels correctly', function () {
        $entry = LogEntry::create(LogLevel::WARNING, 'Warning');

        expect($entry->isLevel(LogLevel::WARNING))->toBeTrue()
            ->and($entry->isLevel(LogLevel::ERROR))->toBeFalse()
            ->and($entry->isLevelOrAbove(LogLevel::ERROR))->toBeFalse()
            ->and($entry->isLevelOrAbove(LogLevel::DEBUG))->toBeTrue();
    });

    it('serializes to JSON', function () {
        $timestamp = new DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $entry = LogEntry::create(LogLevel::INFO, 'Test', ['key' => 'value'], $timestamp, 'test');

        $json = $entry->jsonSerialize();

        expect($json)->toBe([
            'level' => LogLevel::INFO,
            'message' => 'Test',
            'context' => ['key' => 'value'],
            'timestamp' => '2024-01-01T12:00:00+0000',
            'channel' => 'test',
        ]);
    });
});