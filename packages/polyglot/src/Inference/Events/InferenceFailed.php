<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Psr\Log\LogLevel;

class InferenceFailed extends InferenceEvent
{
    public string $logLevel = LogLevel::ERROR;
}