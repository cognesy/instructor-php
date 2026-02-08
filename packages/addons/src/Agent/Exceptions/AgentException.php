<?php declare(strict_types=1);

/**
 * @deprecated Use cognesy/instructor-agents package instead. This class will be removed in a future version.
 */
namespace Cognesy\Addons\Agent\Exceptions;

use Cognesy\Addons\StepByStep\Exceptions\StepByStepException;
use Throwable;

/**
 * @phpstan-consistent-constructor
 */
class AgentException extends StepByStepException
{
    #[\Override]
    public static function fromThrowable(Throwable $throwable): self {
        return new self($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
    }
}
