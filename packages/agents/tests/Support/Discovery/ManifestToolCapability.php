<?php declare(strict_types=1);

namespace Cognesy\Agents\Tests\Support\Discovery;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Capability\Core\UseTools;
use Cognesy\Agents\Tool\Tools\FakeTool;
use Override;

final readonly class ManifestToolCapability implements CanProvideAgentCapability
{
    #[Override]
    public static function capabilityName(): string {
        return 'manifest-tool';
    }

    #[Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return (new UseTools(
            FakeTool::returning('manifest_tool', 'Tool installed by a discovered capability', 'ok'),
        ))->configure($agent);
    }
}
