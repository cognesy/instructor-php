<?php declare(strict_types=1);

namespace Cognesy\Pipeline\Enums;

enum ErrorStrategy {
    case FailFast; // throw an exception immediately on failure
    case ContinueWithFailure; // continue processing with Result set to failure
}
