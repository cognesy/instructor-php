<?php declare(strict_types=1);

namespace Cognesy\Utils\Json\Partial;

interface ParseFrame
{
    /** @return array<mixed> */
    public function getValue(): array;
}