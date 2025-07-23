<?php declare(strict_types=1);

use Cognesy\Http\Middleware\RecordReplay\RecordReplayMiddleware;

// Record all HTTP interactions to a directory
$recordReplayMiddleware = new RecordReplayMiddleware(
    mode: RecordReplayMiddleware::MODE_RECORD,
    storageDir: __DIR__ . '/debug_recordings',
    fallbackToRealRequests: true
);

$client->withMiddleware($recordReplayMiddleware);

// Make your requests...

// Later, you can inspect the recorded files to see what was sent/received
