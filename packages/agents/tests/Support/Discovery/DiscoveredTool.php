<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support\Discovery;

use Cognesy\Agents\Tool\Contracts\CanDescribeTool;
use Cognesy\Agents\Tool\Contracts\ToolInterface;
use Cognesy\Agents\Tool\Tools\FakeTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\Result\Result;
use Override;

final class DiscoveredTool implements ToolInterface
{
    public static int $constructions = 0;

    private FakeTool $tool;

    public function __construct() {
        self::$constructions++;
        $this->tool = FakeTool::returning('discovered-tool', 'Discovered tool', 'ok');
    }

    #[Override]
    public function use(mixed ...$args): Result {
        return $this->tool->use(...$args);
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return $this->tool->toToolSchema();
    }

    #[Override]
    public function descriptor(): CanDescribeTool {
        return $this->tool->descriptor();
    }
}
