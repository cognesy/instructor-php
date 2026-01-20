<?php declare(strict_types=1);

namespace Cognesy\Addons\AgentBuilder\Capabilities\File;

use Cognesy\Addons\AgentBuilder\AgentBuilder;
use Cognesy\Addons\AgentBuilder\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Core\Collections\Tools;

class UseFileTools implements AgentCapability
{
    public function __construct(
        private ?string $baseDir = null,
    ) {}

    #[\Override]
    public function install(AgentBuilder $builder): void {
        $baseDir = $this->baseDir ?? getcwd() ?: '/tmp';

        $fileTools = new Tools(
            ReadFileTool::inDirectory($baseDir),
            WriteFileTool::inDirectory($baseDir),
            EditFileTool::inDirectory($baseDir),
        );

        $builder->withTools($fileTools);
    }
}
