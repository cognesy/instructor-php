<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Core;

use Cognesy\Agents\Builder\Contracts\CanConfigureAgent;
use Cognesy\Agents\Builder\Contracts\CanProvideAgentCapability;
use Cognesy\Agents\Collections\Tools;
use Cognesy\Agents\Tool\Contracts\ToolInterface;

final readonly class UseTools implements CanProvideAgentCapability
{
    /** @var list<ToolInterface> */
    private array $tools;

    public function __construct(ToolInterface ...$tools) {
        $this->tools = $tools;
    }

    #[\Override]
    public static function capabilityName(): string {
        return 'use_tools';
    }

    #[\Override]
    public function configure(CanConfigureAgent $agent): CanConfigureAgent {
        return $agent->withTools(
            $agent->tools()->merge(new Tools(...$this->tools))
        );
    }
}
