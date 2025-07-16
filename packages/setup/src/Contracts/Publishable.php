<?php declare(strict_types=1);

namespace Cognesy\Setup\Contracts;

interface Publishable
{
    public function publish(): bool;
}