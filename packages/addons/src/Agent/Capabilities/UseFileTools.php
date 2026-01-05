<?php declare(strict_types=1);

namespace Cognesy\Addons\Agent\Capabilities;

use Cognesy\Addons\Agent\AgentBuilder;
use Cognesy\Addons\Agent\Collections\Tools;
use Cognesy\Addons\Agent\Contracts\AgentCapability;
use Cognesy\Addons\Agent\Tools\File\EditFileTool;
use Cognesy\Addons\Agent\Tools\File\ReadFileTool;
use Cognesy\Addons\Agent\Tools\File\WriteFileTool;

class UseFileTools implements AgentCapability
{
    public function __construct(
        private ?string $baseDir = null,
    ) {}

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
