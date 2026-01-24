<?php declare(strict_types=1);

namespace Cognesy\Agents\Agent\Exceptions;

use RuntimeException;
use Throwable;

/**
 * @phpstan-consistent-constructor
 */
class AgentException extends RuntimeException
{
    public static function fromThrowable(Throwable $throwable): self {
        return new self($throwable->getMessage(), (int)$throwable->getCode(), $throwable);
    }
}
