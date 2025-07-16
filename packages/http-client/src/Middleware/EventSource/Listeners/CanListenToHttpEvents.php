<?php declare(strict_types=1);

namespace Cognesy\Http\Middleware\EventSource\Listeners;

use Cognesy\Http\Contracts\HttpResponse;
use Cognesy\Http\Data\HttpRequest;

interface CanListenToHttpEvents
{
    public function onRequestReceived(HttpRequest $request): void;

    public function onStreamChunkReceived(HttpRequest $request, HttpResponse $response, string $chunk): void;

    public function onStreamEventAssembled(HttpRequest $request, HttpResponse $response, string $line): void;

    public function onResponseReceived(HttpRequest $request, HttpResponse $response): void;
}