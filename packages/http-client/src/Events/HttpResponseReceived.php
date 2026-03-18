<?php declare(strict_types=1);

namespace Cognesy\Http\Events;

use Psr\Log\LogLevel;

final class HttpResponseReceived extends HttpClientEvent
{
    public string $logLevel = LogLevel::INFO;
}