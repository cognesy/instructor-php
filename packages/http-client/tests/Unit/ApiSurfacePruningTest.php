<?php

use Cognesy\Http\Creation\HttpClientBuilder;
use Cognesy\Http\Drivers\ExtHttp\ExtHttpDriver;
use Cognesy\Http\Drivers\ExtHttp\ExtHttpPool;
use Cognesy\Http\HttpClient;
use Cognesy\Http\Middleware\RecordReplay\StreamedRequestRecord;
use Cognesy\Http\Middleware\ServerSideEvents\ServerSideEventResponseDecorator;
use Cognesy\Http\Middleware\ServerSideEvents\ServerSideEventStream;
use Cognesy\Http\Middleware\ServerSideEvents\StreamSSEsMiddleware;

it('marks compatibility aliases as deprecated', function() {
    $targets = [
        [HttpClient::class, 'withSSEStream'],
        [HttpClientBuilder::class, 'using'],
        [HttpClientBuilder::class, 'withDebugPreset'],
        [StreamedRequestRecord::class, 'createAppropriateRecord'],
    ];

    foreach ($targets as [$class, $method]) {
        $reflection = new ReflectionMethod($class, $method);
        $comment = $reflection->getDocComment() ?: '';
        expect($comment)->toContain('@deprecated');
    }
});

it('marks ServerSideEvents compatibility classes as deprecated', function() {
    $classes = [
        StreamSSEsMiddleware::class,
        ServerSideEventStream::class,
        ServerSideEventResponseDecorator::class,
    ];

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        $comment = $reflection->getDocComment() ?: '';
        expect($comment)->toContain('@deprecated');
    }
});

it('marks optionalization candidates as deprecated', function() {
    $classes = [
        ExtHttpDriver::class,
        ExtHttpPool::class,
    ];

    foreach ($classes as $class) {
        $reflection = new ReflectionClass($class);
        $comment = $reflection->getDocComment() ?: '';
        expect($comment)->toContain('@deprecated');
    }
});

it('avoids new core references to deprecated ServerSideEvents namespace', function() {
    $srcRoot = realpath(__DIR__ . '/../../src');
    expect($srcRoot)->not->toBeFalse();

    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($srcRoot, FilesystemIterator::SKIP_DOTS)
    );

    $violations = [];
    foreach ($iterator as $file) {
        if (!$file->isFile()) {
            continue;
        }
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        if (str_contains($path, '/Middleware/ServerSideEvents/')) {
            continue;
        }

        $contents = file_get_contents($path);
        if ($contents === false) {
            continue;
        }

        if (str_contains($contents, 'Middleware\\ServerSideEvents\\')) {
            $violations[] = $path;
        }
    }

    expect($violations)->toBe([]);
});
