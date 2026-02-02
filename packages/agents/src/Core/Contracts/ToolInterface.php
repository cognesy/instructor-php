<?php declare(strict_types=1);

namespace Cognesy\Agents\Core\Contracts;

use Cognesy\Utils\Result\Result;

interface ToolInterface
{
    public function use(mixed ...$args) : Result;
    public function toToolSchema(): array;
    public function metadata(): array;

    public function name(): string;
    public function description(): string;
    public function instructions(): array;
}