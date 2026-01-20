<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Exceptions;

use RuntimeException;
use Throwable;

/**
 * @phpstan-consistent-constructor
 */
class StepByStepException extends RuntimeException
{
    public static function fromThrowable(Throwable $throwable): self {
        return new self($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
    }
}
