<?php declare(strict_types=1);

namespace Cognesy\Agents\Session\Exceptions;

use RuntimeException;
use Throwable;

final class InvalidSessionFileException extends RuntimeException
{
    public function __construct(
        public readonly string $filePath,
        public readonly string $reason,
        ?Throwable $previous = null,
    ) {
        parent::__construct("Invalid session file [{$filePath}]: {$reason}", 0, $previous);
    }
}
