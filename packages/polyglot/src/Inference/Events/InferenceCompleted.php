<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Psr\Log\LogLevel;

final class InferenceCompleted extends InferenceEvent
{
    public string $logLevel = LogLevel::INFO;
}
