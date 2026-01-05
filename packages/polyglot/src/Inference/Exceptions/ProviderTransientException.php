<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Exceptions;

final class ProviderTransientException extends ProviderException
{
    #[\Override]
    public function isRetriable(): bool {
        return true;
    }
}
