<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Enums;

enum ErrorStrategy {
    case FailFast;            // Stop processing immediately on first error
    case CollectAndFailOnAny; // Continue processing, then return failure if any step fails
    case CollectAndFailOnAll; // Continue processing, return failure only if all steps fail
}
