<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Contracts;

use Cognesy\Utils\Result\Result;

interface ToolInterface
{
    public function name(): string;
    public function description(): string;
    public function use(mixed ...$args) : Result;
    public function toToolSchema(): array;
}