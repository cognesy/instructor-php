<?php declare(strict_types=1);

namespace Cognesy\Polyglot\Inference\Exceptions;

final class ProviderRateLimitException extends ProviderException
{
    #[\Override]
    public function isRetriable(): bool {
        return true;
    }
}
