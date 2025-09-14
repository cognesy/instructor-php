<?php declare(strict_types=1);

namespace Cognesy\Utils\Context\Exceptions;

use RuntimeException;

/**
 * Exception thrown when a requested service is not present in Context.
 */
final class MissingServiceException extends RuntimeException
{
    /** @var class-string */
    private string $class;

    /**
     * @param class-string $class
     */
    public function __construct(string $class) {
        $this->class = $class;
        parent::__construct("Missing service: {$class}");
    }

    /** @return class-string */
    public function class(): string {
        return $this->class;
    }
}
