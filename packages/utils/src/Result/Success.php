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
    public function unwrap() {
        return $this->value;
    }

    public function isSuccess(): bool {
        return true;
    }

    public function isFailure(): bool {
        return false;
    }
}
