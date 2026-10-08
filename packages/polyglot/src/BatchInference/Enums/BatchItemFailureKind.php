<?php

declare(strict_types=1);

namespace Cognesy\Polyglot\BatchInference\Enums;

enum BatchItemFailureKind: string
{
    case ProviderError = 'provider_error';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
