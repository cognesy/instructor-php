<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Events;

use Psr\Log\LogLevel;

final class InferenceAttemptFailed extends InferenceEvent
{
    public string $logLevel = LogLevel::WARNING;
}
