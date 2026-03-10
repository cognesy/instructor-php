<?php declare(strict_types=1);

namespace Cognesy\Agents\Tool\Contracts;

use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\Result\Result;

interface ToolInterface
{
    public function use(mixed ...$args) : Result;

    public function toToolSchema(): ToolDefinition;

    public function descriptor(): CanDescribeTool;
}
