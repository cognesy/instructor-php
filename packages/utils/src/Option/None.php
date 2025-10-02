<?php declare(strict_types=1);

namespace Cognesy\Utils\Option;

/**
 * @extends Option<never>
 */
final readonly class None extends Option
{
    public function __construct() {}

    #[\Override]
    protected function getUnsafe(): never {
        throw new \RuntimeException('Cannot get value from None');
    }
}