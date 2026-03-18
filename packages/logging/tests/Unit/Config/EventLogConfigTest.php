<?php

declare(strict_types=1);

use Cognesy\Logging\Config\EventLogConfig;
use Psr\Log\LogLevel;

describe('EventLogConfig', function () {
    afterEach(function () {
        putenv('INSTRUCTOR_LOG_PATH');
        putenv('INSTRUCTOR_LOG_LEVEL');
        unset($_ENV['INSTRUCTOR_LOG_PATH'], $_ENV['INSTRUCTOR_LOG_LEVEL']);
    });

    it('loads file-backed defaults', function () {
        $path = sys_get_temp_dir() . '/event-log-config-' . uniqid('', true) . '.yaml';
        file_put_contents($path, <<<YAML
path: /tmp/events.jsonl
level: debug
includeEvents:
  - App\\Events\\ImportantEvent
excludeEvents:
  - App\\Events\\DebugEvent
useHierarchyFilter: false
excludeHttpDebug: true
includePayload: false
includeCorrelation: false
includeEventMetadata: true
includeComponentMetadata: false
stringClipLength: 64
YAML);

        $config = EventLogConfig::fromFile($path);

        expect($config->path)->toBe('/tmp/events.jsonl')
            ->and($config->level)->toBe(LogLevel::DEBUG)
            ->and($config->includeEvents)->toBe(['App\\Events\\ImportantEvent'])
            ->and($config->excludeEvents)->toBe(['App\\Events\\DebugEvent'])
            ->and($config->useHierarchyFilter)->toBeFalse()
            ->and($config->excludeHttpDebug)->toBeTrue()
            ->and($config->includePayload)->toBeFalse()
            ->and($config->includeCorrelation)->toBeFalse()
            ->and($config->includeEventMetadata)->toBeTrue()
            ->and($config->includeComponentMetadata)->toBeFalse()
            ->and($config->stringClipLength)->toBe(64);
    });

    it('applies environment overrides on top of file defaults', function () {
        $path = sys_get_temp_dir() . '/event-log-config-' . uniqid('', true) . '.yaml';
        file_put_contents($path, <<<YAML
path: /tmp/default-events.jsonl
level: info
includePayload: false
YAML);

        putenv('INSTRUCTOR_LOG_PATH=/tmp/env-events.jsonl');
        putenv('INSTRUCTOR_LOG_LEVEL=warning');
        $_ENV['INSTRUCTOR_LOG_PATH'] = '/tmp/env-events.jsonl';
        $_ENV['INSTRUCTOR_LOG_LEVEL'] = LogLevel::WARNING;

        $config = EventLogConfig::default($path);

        expect($config->path)->toBe('/tmp/env-events.jsonl')
            ->and($config->level)->toBe(LogLevel::WARNING)
            ->and($config->includePayload)->toBeFalse();
    });
});
