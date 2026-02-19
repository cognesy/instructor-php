<?php declare(strict_types=1);

namespace Cognesy\Agents\Capability\Retrospective;

use Cognesy\Agents\Tool\Tools\StateAwareTool;
use Cognesy\Utils\JsonSchema\JsonSchema;
use Cognesy\Utils\JsonSchema\ToolSchema;

final class ExecutionRetrospectiveTool extends StateAwareTool
{
    public const string TOOL_NAME = 'execution_retrospective';

    public function __construct() {
        parent::__construct(new ExecutionRetrospectiveToolDescriptor());
    }

    #[\Override]
    public function __invoke(mixed ...$args): mixed {
        $checkpointId = (int) $this->arg($args, 'checkpoint_id', 0, -1);
        $guidance = (string) $this->arg($args, 'guidance', 1, '');

        if ($guidance === '') {
            return 'Error: guidance cannot be empty. Provide instructions for your past self.';
        }

        $availableCheckpoints = $this->agentState?->metadata()->get(
            ExecutionRetrospectiveHook::CHECKPOINT_COUNT_KEY, 0
        ) ?? 0;

        if ($checkpointId < 0) {
            return sprintf('Error: checkpoint_id must be >= 0. Got: %d', $checkpointId);
        }

        if ($checkpointId >= $availableCheckpoints) {
            return sprintf(
                'Error: checkpoint_id (%d) does not exist. Available checkpoints: 0 to %d.',
                $checkpointId,
                max(0, $availableCheckpoints - 1),
            );
        }

        return new ExecutionRetrospectiveResult(
            checkpointId: $checkpointId,
            guidance: $guidance,
        );
    }

    #[\Override]
    public function toToolSchema(): array {
        return ToolSchema::make(
            name: $this->name(),
            description: $this->description(),
            parameters: JsonSchema::object('parameters')
                ->withProperties([
                    JsonSchema::integer(
                        'checkpoint_id',
                        'The checkpoint ID to rewind to (visible as CHECKPOINT N in conversation).',
                    ),
                    JsonSchema::string(
                        'guidance',
                        'Message to your past self explaining what you learned, what was tried, '
                        . 'what works, and how to proceed. Account for any side effects already made.',
                    ),
                ])
                ->withRequiredProperties(['checkpoint_id', 'guidance'])
        )->toArray();
    }
}
