<?php declare(strict_types=1);

namespace Cognesy\Agents\AgentBuilder\Capabilities\File;

use Cognesy\Agents\Core\Collections\Tools;
use Cognesy\Agents\AgentBuilder\AgentBuilder;
use Cognesy\Agents\AgentBuilder\Contracts\AgentCapability;

final class UseFileTools implements AgentCapability
{
    public function __construct(
        private string $baseDir,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $baseDir = $this->baseDir;

        $fileTools = new Tools(
            ReadFileTool::inDirectory($baseDir),
            WriteFileTool::inDirectory($baseDir),
            EditFileTool::inDirectory($baseDir),
        );

        $builder->withTools($fileTools);
    }
}
