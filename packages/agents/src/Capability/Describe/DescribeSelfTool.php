<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Describe;

use Cognesy\Agents\Profile\AgentDescription;
use Cognesy\Agents\Profile\AgentProfile;
use Cognesy\Agents\Profile\Contracts\CanAcceptAgentProfile;
use Cognesy\Agents\Tool\Tools\SimpleTool;
use Cognesy\Polyglot\Inference\Data\ToolDefinition;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;
use InvalidArgumentException;
use Override;

final class DescribeSelfTool extends SimpleTool implements CanAcceptAgentProfile
{
    public const TOOL_NAME = 'describe_self';

    public function __construct(private readonly ?AgentProfile $profile = null) {
        parent::__construct(new DescribeSelfToolDescriptor());
    }

    #[Override]
    public function withAgentProfile(AgentProfile $profile): static {
        return new self($profile);
    }

    /** @return array<string, mixed> */
    #[Override]
    public function __invoke(mixed ...$args): array {
        $section = $this->arg($args, 'section', 0, 'all');
        if (!is_string($section)) {
            throw new InvalidArgumentException("'section' must be a string");
        }
        $description = (new AgentDescription($this->profile ?? AgentProfile::empty()))->toArray();
        return match ($section) {
            'all' => $description,
            'tools', 'capabilities', 'hooks' => [$section => $description[$section]],
            default => throw new InvalidArgumentException(
                "Unknown description section '{$section}'. Expected tools, capabilities, hooks, or all.",
            ),
        };
    }

    #[Override]
    public function toToolSchema(): ToolDefinition {
        return ToolDefinition::fromArray(ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')->withProperties([
                JsonSchema::enum(
                    'section',
                    ['tools', 'capabilities', 'hooks', 'all'],
                    'Description section to return. Defaults to all.',
                ),
            ]),
        )->toArray());
    }
}
