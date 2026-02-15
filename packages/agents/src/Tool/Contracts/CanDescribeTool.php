<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Contracts;

interface CanDescribeTool
{
    public function name(): string;

    public function description(): string;

    public function metadata(): array;

    public function instructions(): array;
}
