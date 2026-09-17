<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\Decision\Exceptions;

final class DecisionTransientException extends DecisionProviderException
{
    #[\Override]
    public function isRetriable(): bool
    {
        return true;
    }
}
