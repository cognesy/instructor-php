<?php declare(strict_types=1);

namespace Cognesy\Utils\Result;

/**
 * @template T
 * @extends Result<T, mixed>
 */
final readonly class Success extends Result {
    /**
     * @var T
     */
    private mixed $value;

    /**
     * @param T $value The success value
     */
    public function __construct($value) {
        $this->value = $value;
    }

    /**
     * @return T
     */
    public function unwrap(): mixed {
        return $this->value;
    }

    public function error(): mixed {
        throw new \BadMethodCallException('Cannot call error() on Success');
    }

    public function exception(): \Throwable {
        throw new \BadMethodCallException('Cannot call exception() on Success');
    }

    public function isSuccess(): bool {
        return true;
    }

    public function isFailure(): bool {
        return false;
    }
}
