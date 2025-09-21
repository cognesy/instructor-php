<?php declare(strict_types=1);

namespace Cognesy\Addons\StepByStep\Exceptions;

use RuntimeException;
use Throwable;

class StepByStepException extends RuntimeException
{
    public static function fromThrowable(Throwable $throwable): static {
        return new static($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
    }
}
