<?php declare(strict_types=1);

namespace Cognesy\Addons\Collaboration\Exceptions;

use Cognesy\Addons\StepByStep\Exceptions\StepByStepException;
use Throwable;

/**
 * @phpstan-consistent-constructor
 */
class CollaborationException extends StepByStepException
{
    #[\Override]
    public static function fromThrowable(Throwable $throwable): self {
        return new self($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
    }
}
