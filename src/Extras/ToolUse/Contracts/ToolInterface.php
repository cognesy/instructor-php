<?php

namespace Cognesy\Instructor\Extras\ToolUse\Contracts;

use Cognesy\Instructor\Utils\Result\Result;

interface ToolInterface
{
    public function name(): string;
    public function description(): string;
    public function use(mixed ...$args) : Result;
    public function toToolSchema(): array;
}