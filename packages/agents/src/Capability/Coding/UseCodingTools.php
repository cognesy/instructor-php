<?php

declare(strict_types=1);

namespace Cognesy\Agents\Capability\Coding;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\Bash\BashPolicy;
use Cognesy\Agents\Capability\Bash\BashTool;
use Cognesy\Agents\Capability\File\EditFileTool;
use Cognesy\Agents\Capability\File\ReadFileTool;
use Cognesy\Agents\Capability\File\WriteFileTool;
use Cognesy\Agents\Collections\Tools;
use Override;

final readonly class UseCodingTools implements CanProvideAgentCapability
{
    public function __construct(
        private string $baseDir,
        private ?BashPolicy $bashPolicy = null,
    ) {}

    #[Override]
    public static function capabilityName(): string {
        return 'use_coding_tools';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $tools = new Tools(
            ReadFileTool::inDirectory($this->baseDir, name: 'read'),
            new BashTool(baseDir: $this->baseDir, outputPolicy: $this->bashPolicy),
            EditFileTool::inDirectory($this->baseDir, name: 'edit'),
            WriteFileTool::inDirectory($this->baseDir, name: 'write'),
        );

        return $agent->withTools($agent->tools()->merge($tools));
    }
}
