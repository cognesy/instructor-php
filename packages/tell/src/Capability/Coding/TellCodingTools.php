<?php

declare(strict_types=1);

namespace Cognesy\Tell\Capability\Coding;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\Bash\BashPolicy;
use Cognesy\Agents\Capability\Bash\BashTool;
use Cognesy\Agents\Capability\File\ReadFileTool;
use Cognesy\Agents\Capability\File\WriteFileTool;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Sandbox\Config\ExecutionPolicy;
use Cognesy\Tell\Tell;
use Override;

/**
 * Tell's stable coding-tool vocabulary, with migration aliases over one operation each.
 *
 * @implements CanProvideAgentCapability<CanConfigureAgent>
 */
final readonly class TellCodingTools implements CanProvideAgentCapability
{
    /**
     * @param  string|null  $blobsDir  Tell's spilled-output store for this
     *                                 project. It sits outside the project, so the tools that a spill stub
     *                                 points at are granted it explicitly rather than reaching it by accident.
     */
    public function __construct(
        private string $baseDir,
        private ?BashPolicy $bashPolicy = null,
        private ?string $blobsDir = null,
    ) {}

    #[Override]
    public static function capabilityName(): string {
        return 'tell.coding';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        $bash = $this->bashPolicy ?? new BashPolicy();
        $readable = array_values(array_filter(
            [$this->baseDir, $this->blobsDir],
            static fn (?string $path): bool => $path !== null && $path !== '',
        ));
        $read = ReadFileTool::fromPolicy(
            ExecutionPolicy::in($this->baseDir)
                ->withTimeout(30)
                ->withReadablePaths(...$readable)
                ->inheritEnvironment(),
        );
        $write = new WriteFileTool(baseDir: $this->baseDir);
        $shell = BashTool::withPolicy(
            ExecutionPolicy::in($this->baseDir)
                ->withTimeout($bash->timeout)
                ->withNetwork(false)
                ->withOutputCaps($bash->stdoutLimitBytes, $bash->stderrLimitBytes)
                ->withReadablePaths(...$readable)
                ->inheritEnvironment(),
            $bash,
        );
        $patch = new PatchOperation($this->baseDir);

        $tools = new Tools(
            new StructuredToolAdapter($read, 'read_file', 'read_file', 'read', ['output' => 'line and byte limited']),
            new StructuredToolAdapter($write, 'write_file', 'write_file', 'write', ['newFilesOnly' => true, 'atomic' => true]),
            new ApplyPatchTool($patch),
            new StructuredToolAdapter($shell, 'shell', 'shell', 'execute', ['network' => false, 'sandboxed' => true]),
            new StructuredToolAdapter($read, 'read', 'read_file', 'read', ['output' => 'line and byte limited']),
            new StructuredToolAdapter($write, 'write', 'write_file', 'write', ['newFilesOnly' => true, 'atomic' => true]),
            new EditAliasTool($patch),
            new StructuredToolAdapter($shell, 'bash', 'shell', 'execute', ['network' => false, 'sandboxed' => true]),
        );

        return $agent->withTools($agent->tools()->merge($tools));
    }
}
