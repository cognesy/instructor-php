---
title: 'Symfony Logging Integration'
docname: 'logging_symfony'
path: ''
---

## Overview

Symfony integration with Instructor's functional logging pipeline.

## Example

<?php
require 'examples/boot.php';

use Cognesy\Instructor\StructuredOutput;
use Cognesy\Logging\Pipeline\LoggingPipeline;
use Cognesy\Logging\Filters\LogLevelFilter;
use Cognesy\Logging\Enrichers\LazyEnricher;
use Cognesy\Logging\Formatters\MessageTemplateFormatter;
use Cognesy\Logging\Writers\PsrLoggerWriter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

// Mock Symfony container and request
$container = new Container();
$request = Request::create('/api/extract');
$request->headers->set('X-Request-ID', 'req_' . uniqid());
$request->attributes->set('_route', 'api.extract');

// Create logger
$logger = new Logger('instructor');
$logger->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));

// Create pipeline with Symfony context
$pipeline = LoggingPipeline::create()
    ->filter(new LogLevelFilter('debug'))
    ->enrich(LazyEnricher::framework(fn() => [
        'request_id' => $request->headers->get('X-Request-ID'),
        'route' => $request->attributes->get('_route'),
    ]))
    ->format(new MessageTemplateFormatter([
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputStarted::class =>
            '🎯 [SYMFONY] Starting extraction: {responseClass} (Route: {framework.route})',
        \Cognesy\Instructor\Events\StructuredOutput\StructuredOutputResponseGenerated::class =>
            '✅ [SYMFONY] Completed extraction: {responseClass}',
    ], channel: 'instructor'))
    ->write(new PsrLoggerWriter($logger))
    ->build();

echo "🔧 Symfony logging pipeline configured\n";
echo "📋 About to execute StructuredOutput with logging...\n\n";

class User
{
    public int $age;
    public string $name;
}

// Extract data with logging
echo "🚀 Starting StructuredOutput extraction...\n";
$user = (new StructuredOutput)
    ->using('openai')
    ->wiretap($pipeline)
    ->withMessages("Jason is 25 years old.")
    ->withResponseClass(User::class)
    ->get();

echo "\n✅ Extraction completed!\n";
echo "📊 Result: User: {$user->name}, Age: {$user->age}\n";