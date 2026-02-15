<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\File;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;

final class UseFileTools implements CanProvideAgentCapability
{
    public function __construct(
        private string $baseDir,
    ) {}

    #[\Override]
    public static function capabilityName(): string {
        return 'use_file_tools';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $baseDir = $this->baseDir;

        $fileTools = new Tools(
            ReadFileTool::inDirectory($baseDir),
            WriteFileTool::inDirectory($baseDir),
            EditFileTool::inDirectory($baseDir),
        );

        return $agent->withTools($agent->tools()->merge($fileTools));
    }
}
