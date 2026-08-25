<?php declare(strict_types=1);

namespace Cognesy\Agents\Exceptions;

use RuntimeException;
use Throwable;

class AgentException extends RuntimeException
{
    public function __construct(string $message, ?Throwable $previous = null) {
        parent::__construct($message, $previous?->getCode() ?? 0, $previous);
    }

    public static function fromError(Throwable $throwable): self {
        return new self($throwable->getMessage(), $throwable);
    }
}
