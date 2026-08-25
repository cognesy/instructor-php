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
use Override;

/**
 * Tell's stable coding-tool vocabulary, with migration aliases over one operation each.
 *
 * @implements CanProvideAgentCapability<CanConfigureAgent>
 */
final readonly class TellCodingTools implements CanProvideAgentCapability
{
    public function __construct(
        private string $baseDir,
        private ?BashPolicy $bashPolicy = null,
    ) {}

    #[Override]
    public static function capabilityName(): string
    {
        return 'tell.coding';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent
    {
        $read = new ReadFileTool(baseDir: $this->baseDir);
        $write = new WriteFileTool(baseDir: $this->baseDir);
        $shell = new BashTool(baseDir: $this->baseDir, outputPolicy: $this->bashPolicy);
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
