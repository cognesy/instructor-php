<?php declare(strict_types=1);

namespace Cognesy\Utils\Option;

/**
 * @extends Option<never>
 */
final readonly class None extends Option
{
    public function __construct() {}

    protected function getUnsafe(): mixed {
        return null; // None has no value
    }
}