<?php declare(strict_types=1);

namespace Cognesy\Utils\Option;

/**
 * @template T
 * @extends Option<T>
 */
final readonly class Some extends Option
{
    /** @param T $value */
    public function __construct(private mixed $value) {}

    protected function getUnsafe(): mixed {
        return $this->value;
    }
}